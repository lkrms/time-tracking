<?php declare(strict_types=1);

namespace Lkrms\Time;

use Closure;
use DateTimeInterface;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lkrms\Auth\Concern\GetsOAuth2AccessToken;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IServiceShared;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\CurlerBuilder;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\Pager\QueryPager;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\DateParser\RegexDateParser;
use Lkrms\Support\Dictionary\HttpHeader;
use Lkrms\Support\Http\HttpServer;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Contract\ISyncContext as Context;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Support\HttpSyncDefinition as Definition;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder as DefinitionBuilder;
use Lkrms\Sync\Support\SyncOperation as OP;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Invoice;
use Lkrms\Time\Entity\Provider\InvoiceProvider;
use RuntimeException;
use UnexpectedValueException;

/**
 * @property-read string $TenantIdKey
 * @method Invoice createInvoice(SyncContext $ctx, Invoice $invoice)
 * @method Invoice getInvoice(SyncContext $ctx, int|string|null $id)
 * @method iterable<Invoice> getInvoices(SyncContext $ctx)
 * @method Client getClient(SyncContext $ctx, int|string|null $id)
 * @method iterable<Client> getClients(SyncContext $ctx)
 */
final class XeroProvider extends HttpSyncProvider implements IServiceShared, IReadable, InvoiceProvider
{
    use GetsOAuth2AccessToken;

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
            'ContactID'    => 'Id',
            'EmailAddress' => 'Email',
        ],
        Invoice::class => [
            'Contact'      => 'Client',
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
            'name'  => 'Name',
            'email' => 'EmailAddress'
        ],
        Invoice::class => [
            'number'    => 'InvoiceNumber',
            'reference' => 'Reference',
            'date'      => 'Date',
            'due_date'  => 'DueDate',
            'status'    => 'Status',
        ],
    ];

    /**
     * OAuth2 scopes required for the provider to perform sync operations
     *
     * @var string[]
     * @link https://developer.xero.com/documentation/oauth2/scopes
     */
    private const OAUTH2_SCOPES = [
        'openid',
        'email',
        'profile',
        'offline_access',
        'accounting.contacts',
        'accounting.transactions',
    ];

    /**
     * @var string|null
     */
    private $_TenantIdKey;

    protected function getOAuth2Listener(): HttpServer
    {
        $env = $this->env();

        $listener = new HttpServer(
            $env->get('app_host', 'localhost'),
            $env->getInt('app_port', 27755)
        );

        $proxyHost = $env->get('app_proxy_host', null);
        $proxyPort = $env->getInt('app_proxy_port', null);

        if ($proxyHost && !is_null($proxyPort)) {
            return $listener->withProxy(
                $proxyHost,
                $proxyPort,
                $env->getBool('app_proxy_tls', null)
            );
        }

        return $listener;
    }

    protected function getOAuth2Provider(): AbstractProvider
    {
        return new GenericProvider([
            'clientId'                => $this->env()->get('xero_app_client_id'),
            'clientSecret'            => $this->env()->get('xero_app_client_secret'),
            'redirectUri'             => $this->OAuth2RedirectUri,
            'urlAuthorize'            => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken'          => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://identity.xero.com/connect/userinfo',
            'scopes'                  => self::OAUTH2_SCOPES,
            'scopeSeparator'          => ' ',
        ]);
    }

    protected function getOAuth2JsonWebKeySetUrl(): string
    {
        return 'https://identity.xero.com/.well-known/openid-configuration/jwks';
    }

    protected function receiveOAuth2Token(AccessTokenInterface $token): void {}

    public function getBackendIdentifier(): array
    {
        return [
            $this->requireTenantId(),
        ];
    }

    protected function getDateFormatter(): DateFormatter
    {
        return new DateFormatter(DateTimeInterface::ATOM, null, RegexDateParser::dotNet());
    }

    public static function getContextualBindings(): array
    {
        return [
            Invoice::class => \Lkrms\Time\Entity\Xero\Invoice::class,
        ];
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        $connections = $this->getConnections($ttl);
        $count       = count($connections);

        Console::debug(
            sprintf('Connected to Xero with %d %s:',
                    $count,
                    Convert::plural($count, 'tenant connection')),
            $this->getFormattedTenantList($connections)
        );

        return $this;
    }

    protected function buildCurler(CurlerBuilder $curlerB): CurlerBuilder
    {
        return $curlerB->alwaysPaginate();
    }

    protected function getHttpDefinition(string $entity, DefinitionBuilder $defB): DefinitionBuilder
    {
        $defB = $defB
            ->path(sprintf('/api.xro/2.0/%s', self::ENTITY_PATH_MAP[$entity] ?? $entity::plural()))
            ->callback(fn(Definition $def, int $op, Context $ctx): Definition =>
                           $def->withPager(new QueryPager('page', self::ENTITY_SELECTOR_MAP[$entity] ?? $entity::plural(), 100))
                               ->if($op === OP::READ_LIST, fn(Definition $def) => $def->withQuery($this->buildQuery($ctx, self::ENTITY_QUERY_MAPS[$entity]))));

        switch ($entity) {
            case Invoice::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST, OP::CREATE])
                    ->dataToEntityPipeline($this->pipeline()
                                                ->throughKeyMap(self::ENTITY_KEY_MAPS[$entity])
                                                ->through(fn(array $payload, Closure $next) =>
                                                              $payload['Type'] === 'ACCREC' ? $next($payload) : null))
                    ->entityToDataPipeline($this->pipeline()
                                                ->after(fn(Invoice $invoice) =>
                                                            $this->generateInvoice($invoice)));

            case Client::class:
                return $defB
                    ->operations([OP::READ, OP::READ_LIST])
                    ->dataToEntityPipeline($this->pipeline()
                                                ->throughKeyMap(self::ENTITY_KEY_MAPS[$entity]));
        }

        return $defB;
    }

    protected function getBaseUrl(?string $path = null): string
    {
        return 'https://api.xero.com';
    }

    protected function getHeaders(?string $path): ?ICurlerHeaders
    {
        if ($path === '/connections') {
            $tenantId    = null;
            $accessToken = $this->getAccessToken(self::OAUTH2_SCOPES);
        } else {
            if (!($tenantId = $this->getTenantId())) {
                Console::warn(
                    "Environment variable 'xero_tenant_id' should be set to one of the following GUIDs:",
                    $this->getFormattedTenantList($this->getConnections())
                );
                throw new RuntimeException('No tenant ID');
            }
            $accessToken = $this->getAccessToken(self::OAUTH2_SCOPES);
        }

        $headers = CurlerHeaders::create()
            ->setHeader(HttpHeader::AUTHORIZATION,
                        'Bearer ' . $accessToken->Token);
        if ($tenantId) {
            return $headers->setHeader('Xero-Tenant-Id', $tenantId);
        }

        return $headers;
    }

    protected function _getTenantIdKey(): string
    {
        return $this->_TenantIdKey
                   ?: ($this->_TenantIdKey =
                       implode(':', [
                           static::class,
                           'tenant',
                           $this->getBaseUrl(),
                           'uuid',
                       ]));
    }

    private function getConnections(int $ttl = -1): array
    {
        // See https://developer.xero.com/documentation/guides/oauth2/tenants
        return $this->getCurler('/connections', $ttl)->get();
    }

    /**
     * @param array<array<string,mixed>> $connections
     */
    private function getFormattedTenantList(array $connections): string
    {
        return implode("\n", array_map(
            fn($conn) =>
                sprintf('- %s ~~(%s)~~', $conn['tenantId'], $conn['tenantName']),
            $connections
        ));
    }

    private function requireTenantId(): string
    {
        if ($tenantId = $this->getTenantId()) {
            return $tenantId;
        }

        throw new RuntimeException('No tenant ID');
    }

    private function getTenantId(): ?string
    {
        // Flush the token cache to trigger [re-]authorization if a cached or
        // configured tenant ID isn't in the connections list
        if (!$this->checkTenantAccess()) {
            $this->flushAccessToken();
            $this->flushTenantId();
            $flushed = true;
        }

        if ($tenantId = $this->getPreferredTenantId(false)) {
            if (!($flushed ?? false)) {
                $this->flushTenantId();
            }

            return $tenantId;
        }

        // If there is no tenant ID in the environment, try to get one from the
        // cache or via authorization
        if ($tenantId = Cache::get($this->TenantIdKey) ?: null) {
            return $tenantId;
        }

        $token   = $this->authorize();
        $eventId = $token->Claims['authentication_event_id'];

        // If a connection was "newly authorized in the current auth flow", or
        // only one connection exists, we can safely use its tenantId in lieu of
        // an explicit configuration
        $connection = array_filter(
            $connections = $this->getConnections(),
            fn(array $conn) =>
                $conn['authEventId'] == $eventId
        );
        if (!$connection && count($connections) === 1) {
            $connection = $connections;
        }
        if (!$connection) {
            return null;
        }

        $connection = array_pop($connection);
        $tenantId   = $connection['tenantId'];
        Cache::set($this->TenantIdKey, $tenantId);

        return $tenantId;
    }

    private function checkTenantAccess(?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: $this->getPreferredTenantId();
        if (!$tenantId ||
                array_filter(
                    $connections = $this->getConnections(),
                    fn(array $conn) => !strcasecmp($conn['tenantId'], $tenantId)
                )) {
            return true;
        }

        Console::warn(
            sprintf("Not connected to Xero tenant '%s'; tenant connections:",
                    $tenantId),
            $this->getFormattedTenantList($connections)
        );

        return false;
    }

    private function getPreferredTenantId(bool $allowCache = true): ?string
    {
        $tenantId = $this->env()->get('xero_tenant_id', null);
        if (!$tenantId && $allowCache) {
            return Cache::get($this->TenantIdKey) ?: null;
        }

        return $tenantId ?: null;
    }

    /**
     * @return $this
     */
    private function flushTenantId()
    {
        Cache::delete($this->TenantIdKey);

        return $this;
    }

    private function buildQuery(Context $ctx, array $fieldMap): array
    {
        $query = [
            'page' => 1
        ];

        $where = [];
        foreach ($fieldMap as $filterField => $field) {
            if (is_null($values = $ctx->claimFilterValue($_filterField = $filterField)) &&
                    is_null($values = $ctx->claimFilterValue($_filterField = "!$filterField"))) {
                continue;
            }

            [$prefix, $eq, $glue] = $_filterField === $filterField
                                        ? ['', '==', ' OR ']
                                        : ['NOT ', '!=', ' AND '];

            // TODO: escape each $value
            $where[$field] = array_map(
                function ($value) use ($field, $prefix, $eq) {
                    $expr = preg_replace(
                        ['/^\*([^*]*)\*$/', '/^([^*]*)\*$/', '/^\*([^*]*)$/'],
                        [
                            "{$prefix}{$field}.Contains(\"\$1\")",
                            "{$prefix}{$field}.StartsWith(\"\$1\")",
                            "{$prefix}{$field}.EndsWith(\"\$1\")",
                        ],
                        $value
                    );
                    if ($expr === $value) {
                        return "{$field}{$eq}\"$value\"";
                    }

                    return $expr;
                },
                Convert::toArray($values)
            );
            $where[$field]['__glue'] = $glue;
        }
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

        if ($orderby = $ctx->claimFilterValue('$orderby')) {
            $parts = [];
            // Format: "<field_name>[ (ASC|DESC)][,...]"
            foreach (explode(',', $orderby) as $expr) {
                $expr = preg_split('/\h+/', trim($expr));
                if (count($expr) > 2) {
                    throw new UnexpectedValueException("Invalid \$orderby value '{$orderby}'");
                }
                if ($field = $fieldMap[$expr[0]] ?? null) {
                    $parts[] = $field . (strtoupper($expr[1] ?? '') == 'DESC' ? ' DESC' : '');
                }
            }
            if ($parts) {
                $query['order'] = implode(',', $parts);
            }
        }

        return $query;
    }

    private function generateInvoice(Invoice $invoice): array
    {
        $data                  = [];
        $data['Type']          = 'ACCREC';
        $data['Contact']       = ['ContactID' => $invoice->Client->Id];
        $data['InvoiceNumber'] = $invoice->Number;
        $data['Reference']     = $invoice->Reference;
        $data['Date']          = $invoice->Date;
        $data['DueDate']       = $invoice->DueDate;
        $data['LineItems']     = [];
        $data['Status']        = $invoice->Status;

        $data = array_filter($data, fn($value) => !is_null($value));

        foreach ($invoice->LineItems as $lineItem) {
            $line                = [];
            $line['Description'] = $lineItem->Description;
            $line['Quantity']    = $lineItem->Quantity;
            $line['UnitAmount']  = $lineItem->UnitAmount;
            $line['ItemCode']    = $lineItem->ItemCode;
            $line['AccountCode'] = $lineItem->AccountCode;
            $line['Tracking']    = $lineItem->Tracking;

            $line = array_filter($line, fn($value) => !is_null($value));

            $data['LineItems'][] = $line;
        }

        return $data;
    }
}
