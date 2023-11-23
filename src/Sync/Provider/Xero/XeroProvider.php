<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Provider\Xero;

use Lkrms\Auth\AccessToken;
use Lkrms\Auth\OAuth2GrantType;
use Lkrms\Contract\IDateFormatter;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Pager\QueryPager;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Support\DateParser\RegexDateParser;
use Lkrms\Support\DateFormatter;
use Lkrms\Sync\Catalog\SyncOperation as OP;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Contract\ISyncContext as Context;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Support\HttpSyncDefinition as HttpDef;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder as HttpDefB;
use Lkrms\Time\Sync\ContractGroup\InvoiceProvider;
use Lkrms\Time\Sync\Entity\Client;
use Lkrms\Time\Sync\Entity\Invoice;
use Lkrms\Utility\Convert;
use Closure;
use DateTimeInterface;
use RuntimeException;
use UnexpectedValueException;

/**
 * @method Invoice createInvoice(ISyncContext $ctx, Invoice $invoice)
 * @method Invoice getInvoice(ISyncContext $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Invoice> getInvoices(ISyncContext $ctx)
 * @method Client getClient(ISyncContext $ctx, int|string|null $id)
 * @method FluentIteratorInterface<array-key,Client> getClients(ISyncContext $ctx)
 */
final class XeroProvider extends HttpSyncProvider implements
    IServiceSingleton,
    InvoiceProvider
{
    /**
     * Entity => endpoint path
     *
     * @var array<class-string<ISyncEntity>,string>
     */
    private const ENTITY_PATH_MAP = [
        Client::class => 'Contacts',
    ];

    /**
     * Entity => result selector
     *
     * @var array<class-string<ISyncEntity>,string>
     */
    private const ENTITY_SELECTOR_MAP = [
        Client::class => 'Contacts',
    ];

    /**
     * Entity => [ provider key => entity property ]
     *
     * @var array<class-string<ISyncEntity>,array<string,string>>
     */
    private const ENTITY_KEY_MAPS = [
        Client::class => [
            'ContactID' => 'Id',
            'EmailAddress' => 'Email',
        ],
        Invoice::class => [
            'Contact' => 'Client',
            'CurrencyCode' => 'Currency',
        ],
    ];

    /**
     * Entity => [ snake_case filter => provider search term ]
     *
     * @var array<class-string<ISyncEntity>,array<string,string>>
     */
    private const ENTITY_QUERY_MAPS = [
        Client::class => [
            'name' => 'Name',
            'email' => 'EmailAddress'
        ],
        Invoice::class => [
            'number' => 'InvoiceNumber',
            'reference' => 'Reference',
            'date' => 'Date',
            'due_date' => 'DueDate',
            'status' => 'Status',
        ],
    ];

    /**
     * OAuth2 scopes required for the provider to perform sync operations
     *
     * @link https://developer.xero.com/documentation/oauth2/scopes
     *
     * @var string[]
     */
    private const OAUTH2_SCOPES = [
        'accounting.contacts',
        'accounting.transactions',
    ];

    private XeroOAuth2Client $OAuth2Client;

    private string $TenantIdKey;

    /**
     * @var array<array{id:string,authEventId:string,tenantId:string,tenantType:string,tenantName:string,createdDateUtc:string,updatedDateUtc:string}>|null
     */
    private ?array $Connections;

    public function name(): string
    {
        return sprintf('Xero { %s }', $this->requireTenantId());
    }

    public function getBackendIdentifier(): array
    {
        return [
            $this->requireTenantId(),
        ];
    }

    protected function getDateFormatter(?string $path = null): IDateFormatter
    {
        return new DateFormatter(
            DateTimeInterface::ATOM,
            null,
            RegexDateParser::dotNet(),
        );
    }

    protected function getHeartbeat()
    {
        $connections = $this->getConnections();

        $count = count($connections);
        Console::debug(sprintf(
            'Connected to Xero with %d %s:',
            $count,
            Convert::plural($count, 'tenant connection')
        ), $this->formatTenantList($connections, false));

        return $connections;
    }

    protected function buildCurler(CurlerBuilder $curlerB): CurlerBuilder
    {
        return $curlerB->alwaysPaginate();
    }

    protected function buildHttpDefinition(string $entity, HttpDefB $defB): HttpDefB
    {
        $defB =
            $defB
                ->path(sprintf(
                    '/api.xro/2.0/%s',
                    self::ENTITY_PATH_MAP[$entity] ?? $entity::plural(),
                ))
                ->pager(new QueryPager(
                    'page',
                    self::ENTITY_SELECTOR_MAP[$entity] ?? $entity::plural(),
                    100,
                ))
                ->callback(
                    fn(HttpDef $def, $op, Context $ctx) =>
                        match ($op) {
                            OP::READ_LIST => $def->withQuery(
                                $this->buildQuery(
                                    $ctx, self::ENTITY_QUERY_MAPS[$entity]
                                )
                            ),

                            default => $def,
                        }
                );

        return match ($entity) {
            Invoice::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST, OP::CREATE])
                    ->pipelineFromBackend(
                        $this->pipeline()
                             ->throughKeyMap(self::ENTITY_KEY_MAPS[$entity])
                             ->through(
                                 // Discard accounts payable invoices
                                 fn(array $payload, Closure $next) =>
                                     $payload['Type'] === 'ACCREC'
                                         ? $next($payload)
                                         : null
                             )
                    )
                    ->pipelineToBackend(
                        $this->pipeline()
                             ->after(
                                 fn(Invoice $invoice) =>
                                     $this->generateInvoice($invoice)
                             )
                    ),

            Client::class =>
                $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->keyMap(self::ENTITY_KEY_MAPS[$entity]),

            default =>
                $defB,
        };
    }

    protected function getBaseUrl(?string $path = null): string
    {
        return 'https://api.xero.com';
    }

    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        if ($path === '/connections') {
            $tenantId = null;
            $accessToken = $this->getAccessToken();
        } else {
            $tenantId = $this->getTenantId();
            if ($tenantId === null) {
                Console::warn(
                    "Environment variable 'xero_tenant_id' should be set to one of the following GUIDs:",
                    $this->formatTenantList($this->getConnections())
                );
                throw new RuntimeException('No tenant ID');
            }
            $accessToken = $this->getAccessToken();
        }

        $headers =
            CurlerHeaders::create()
                ->applyAccessToken($accessToken);

        if ($tenantId !== null) {
            return $headers->setHeader('Xero-Tenant-Id', $tenantId);
        }

        return $headers;
    }

    /**
     * @return array<array{id:string,authEventId:string,tenantId:string,tenantType:string,tenantName:string,createdDateUtc:string,updatedDateUtc:string}>
     */
    private function getConnections(): array
    {
        // See https://developer.xero.com/documentation/guides/oauth2/tenants
        return $this->Connections
            ?? ($this->Connections = $this->getCurler('/connections')->get());
    }

    /**
     * @param array<array{id:string,authEventId:string,tenantId:string,tenantType:string,tenantName:string,createdDateUtc:string,updatedDateUtc:string}> $connections
     */
    private function formatTenantList(array $connections, bool $withMarkup = true): string
    {
        $d = $withMarkup ? '~~' : '';

        $format =
            count($connections) > 1
                ? "- %s {$d}(%s){$d}"
                : "%s {$d}(%s){$d}";

        return
            implode("\n", array_map(
                fn($conn) =>
                    sprintf($format, $conn['tenantId'], $conn['tenantName']),
                $connections,
            ));
    }

    private function requireTenantId(): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null) {
            throw new RuntimeException('No tenant ID');
        }
        return $tenantId;
    }

    private function getTenantId(): ?string
    {
        $flushed = false;

        $tenantId = $this->Env->getNullable('xero_tenant_id', null);
        if ($tenantId !== null) {
            // Remove a previously cached tenant ID if there is a tenant ID in
            // the environment
            $this->flushTenantId();
            $flushed = true;
            if ($this->checkTenantAccess($tenantId)) {
                return $tenantId;
            }
        } else {
            $tenantId = Cache::getString($this->getTenantIdKey());
            if ($tenantId !== null && $this->checkTenantAccess($tenantId)) {
                return $tenantId;
            }
        }

        // Flush the token cache to trigger [re-]authorization if a cached or
        // configured tenant ID isn't in the connections list
        if ($tenantId !== null) {
            $this->getOAuth2Client()->flushTokens();
            if (!$flushed) {
                $this->flushTenantId();
            }
        }

        $token = $this->getAccessToken();
        $eventId = $token->Claims['authentication_event_id'] ?? null;

        // If a connection was "newly authorized in the current auth flow", or
        // only one connection exists, we can safely use its tenantId in lieu of
        // an explicit configuration
        $connections = $this->getConnections();
        $connection = array_filter(
            $connections,
            fn(array $conn) =>
                $eventId === $conn['authEventId']
        );

        if (!$connection && count($connections) === 1) {
            $connection = $connections;
        }

        if (!$connection) {
            return null;
        }

        $connection = array_pop($connection);
        $tenantId = $connection['tenantId'];
        Cache::set($this->getTenantIdKey(), $tenantId);

        return $tenantId;
    }

    /**
     * False if the current user cannot access the current tenant
     *
     * Returns `true` if there is no cached or configured tenant ID.
     */
    private function checkTenantAccess(string $tenantId): bool
    {
        $connections = $this->getConnections();
        if (array_filter(
            $connections,
            fn(array $conn) => !strcasecmp($conn['tenantId'], $tenantId)
        )) {
            return true;
        }

        Console::warn(sprintf(
            "Not connected to Xero tenant '%s'; tenant connections:",
            $tenantId,
        ), $this->formatTenantList($connections));

        return false;
    }

    private function flushTenantId(): void
    {
        Cache::delete($this->getTenantIdKey());
    }

    /**
     * @param array<string,string> $fieldMap
     * @return array<string,mixed>
     */
    private function buildQuery(Context $ctx, array $fieldMap): array
    {
        $query = [
            'page' => 1,
        ];

        // Prepare a "where" parameter by claiming recognised values from the
        // filter and converting them to Xero's query language
        $where = [];
        foreach ($fieldMap as $filterField => $field) {
            $_filterField = $filterField;
            $values = $ctx->claimFilter($_filterField);

            if ($values === null) {
                $_filterField = "!$filterField";
                $values = $ctx->claimFilter($_filterField);

                if ($values === null) {
                    continue;
                }
            }

            if ($_filterField === $filterField) {
                [$prefix, $eq, $glue] = ['', '==', ' OR '];
            } else {
                [$prefix, $eq, $glue] = ['NOT ', '!=', ' AND '];
            }

            // TODO: escape each $value
            $where[$field] = array_map(
                function ($value) use ($field, $prefix, $eq) {
                    // Map wildcards to Contains, StartsWith or EndsWith if they
                    // appear around, after or before other text, respectively
                    $expr = preg_replace(
                        [
                            '/^\*([^*]++)\*$/',
                            '/^([^*]++)\*$/',
                            '/^\*([^*]++)$/',
                        ],
                        [
                            "{$prefix}{$field}.Contains(\"\$1\")",
                            "{$prefix}{$field}.StartsWith(\"\$1\")",
                            "{$prefix}{$field}.EndsWith(\"\$1\")",
                        ],
                        $value,
                        -1,
                        $count,
                    );
                    if (!$count) {
                        return "{$field}{$eq}\"$value\"";
                    }
                    return $expr;
                },
                (array) $values
            );
            $where[$field]['__glue'] = $glue;
        }

        // Reduce `$where` to a string, using parentheses to separate
        // expressions if necessary
        if ($where) {
            $parts = [];
            foreach ($where as $group) {
                $glue = $group['__glue'];
                unset($group['__glue']);
                $expr = implode($glue, $group);
                if (count($where) > 1 && count($group) > 1) {
                    $expr = "($expr)";
                }
                $parts[] = $expr;
            }
            $query['where'] = implode(' AND ', $parts);
        }

        // Add an "order" parameter if an "$orderby" filter is provided
        $orderby = $ctx->claimFilter('$orderby');
        if ($orderby !== null) {
            $parts = [];
            // Format: "<field_name>[ (ASC|DESC)][,...]"
            foreach (explode(',', $orderby) as $expr) {
                $expr = preg_split('/\h+/', trim($expr));
                if (count($expr) > 2) {
                    throw new UnexpectedValueException(
                        sprintf('Invalid $orderby value: %s', $orderby)
                    );
                }
                $field = $fieldMap[$expr[0]] ?? null;
                if ($field !== null) {
                    $ascDesc = strtoupper($expr[1] ?? '');
                    $parts[] = $field . ($ascDesc === 'DESC' ? ' DESC' : '');
                }
            }
            if ($parts) {
                $query['order'] = implode(',', $parts);
            }
        }

        return $query;
    }

    /**
     * @return array<string,mixed>
     */
    private function generateInvoice(Invoice $invoice): array
    {
        $data = [];
        $data['Type'] = 'ACCREC';
        $data['Contact'] = ['ContactID' => $invoice->Client->Id];
        $data['InvoiceNumber'] = $invoice->Number;
        $data['Reference'] = $invoice->Reference;
        $data['Date'] = $invoice->Date;
        $data['DueDate'] = $invoice->DueDate;
        $data['LineItems'] = [];
        $data['Status'] = $invoice->Status;

        $data = array_filter($data, fn($value) => $value !== null);

        foreach ($invoice->LineItems as $lineItem) {
            $line = [];
            $line['Description'] = $lineItem->Description;
            $line['Quantity'] = $lineItem->Quantity;
            $line['UnitAmount'] = $lineItem->UnitAmount;
            $line['ItemCode'] = $lineItem->ItemCode;
            $line['AccountCode'] = $lineItem->AccountCode;
            $line['Tracking'] = $lineItem->Tracking;

            $line = array_filter($line, fn($value) => $value !== null);

            $data['LineItems'][] = $line;
        }

        return $data;
    }

    private function getAccessToken(): AccessToken
    {
        return $this->getOAuth2Client()->getAccessToken(self::OAUTH2_SCOPES);
    }

    private function getOAuth2Client(): XeroOAuth2Client
    {
        return $this->OAuth2Client
            ?? ($this->OAuth2Client =
                $this->App
                     ->get(XeroOAuth2Client::class)
                     ->withDefaultScopes(self::OAUTH2_SCOPES)
                     ->withCallback(
                         function (AccessToken $token, ?array $idToken, string $grantType) {
                             if ($grantType !== OAuth2GrantType::REFRESH_TOKEN) {
                                 $this->Connections = null;
                             }
                         }
                     ));
    }

    private function getTenantIdKey(): string
    {
        return $this->TenantIdKey
            ?? ($this->TenantIdKey = implode(':', [
                static::class,
                'tenant',
                $this->getBaseUrl(),
                'uuid',
            ]));
    }
}
