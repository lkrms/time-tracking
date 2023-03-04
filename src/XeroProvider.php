<?php

declare(strict_types=1);

namespace Lkrms\Time;

use Closure;
use DateTimeInterface;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lkrms\Container\Container;
use Lkrms\Contract\IServiceShared;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Curler\Pager\QueryPager;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Console;
use Lkrms\Facade\Convert;
use Lkrms\Facade\Env;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\DateParser\RegexDateParser;
use Lkrms\Support\HttpHeader;
use Lkrms\Support\HttpRequest;
use Lkrms\Support\HttpResponse;
use Lkrms\Support\HttpServer;
use Lkrms\Support\Pipeline;
use Lkrms\Sync\Concept\HttpSyncProvider;
use Lkrms\Sync\Support\HttpSyncDefinitionBuilder;
use Lkrms\Sync\Support\SyncContext as Context;
use Lkrms\Sync\Support\SyncOperation as OP;
use Lkrms\Sync\Support\SyncStore;
use Lkrms\Time\Entity\Client;
use Lkrms\Time\Entity\Invoice;
use Lkrms\Time\Entity\Provider\InvoiceProvider;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

/**
 * @method Invoice createInvoice(SyncContext $ctx, Invoice $invoice)
 * @method Invoice getInvoice(SyncContext $ctx, int|string|null $id)
 * @method iterable<Invoice> getInvoices(SyncContext $ctx)
 * @method Client getClient(SyncContext $ctx, int|string|null $id)
 * @method iterable<Client> getClients(SyncContext $ctx)
 */
class XeroProvider extends HttpSyncProvider implements IServiceShared, InvoiceProvider
{
    private const SYNC_ENTITY_KEY_MAPS = [
        Client::class      => [
            "ContactID"    => "Id",
            "EmailAddress" => "Email",
        ],
        Invoice::class     => [
            "Contact"      => "Client",
            "CurrencyCode" => "Currency",
        ],
    ];

    private const QUERY_FIELD_MAPS = [
        Client::class => [
            "name"    => "Name",
            "email"   => "EmailAddress"
        ],
        Invoice::class  => [
            "number"    => "InvoiceNumber",
            "reference" => "Reference",
            "date"      => "Date",
            "due_date"  => "DueDate",
            "status"    => "Status",
        ],
    ];

    /**
     * @var string[]
     * @link https://developer.xero.com/documentation/oauth2/scopes
     */
    private const OAUTH2_SCOPES = [
        "openid",
        "email",
        "profile",
        "offline_access",
        "accounting.contacts",
        "accounting.transactions",
    ];

    /**
     * @var HttpServer
     */
    private $OAuth2Listener;

    /**
     * @var GenericProvider
     */
    private $OAuth2Provider;

    /**
     * @var string|null
     */
    private $OAuth2State;

    /**
     * @var string
     */
    private $TokenKey;

    /**
     * @var string
     */
    private $TenantIdKey;

    public static function getContextualBindings(): array
    {
        return [
            Invoice::class => \Lkrms\Time\Entity\Xero\Invoice::class
        ];
    }

    public function __construct(Container $container, SyncStore $store)
    {
        $host = Env::get("app_host", "localhost");
        $port = (int)Env::get("app_port", "27755");

        $this->OAuth2Listener = new HttpServer($host, $port);

        $this->OAuth2Provider = new GenericProvider([
            "clientId"       => Env::get("xero_app_client_id"),
            "clientSecret"   => Env::get("xero_app_client_secret"),
            "redirectUri"    => "http://$host:$port/oauth2/callback",
            "urlAuthorize"   => "https://login.xero.com/identity/connect/authorize",
            "urlAccessToken" => "https://identity.xero.com/connect/token",
            "urlResourceOwnerDetails" => null,
        ]);

        $this->TokenKey    = "token/" . $this->getBaseUrl();
        $this->TenantIdKey = "uuid/" . $this->getBaseUrl() . "/tenant";

        // Don't call parent::__construct() until $TokenKey is set, otherwise
        // the entity store will request our backend ID, one thing will lead to
        // another, and getAccessToken() will fail because $TokenKey is null
        parent::__construct($container, $store);
    }

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

    protected function getBaseUrl(?string $path = null): string
    {
        return "https://api.xero.com";
    }

    protected function getCurlerHeaders(?string $path): ?CurlerHeaders
    {
        if ($path == "/connections")
        {
            $tenantId    = null;
            $accessToken = $this->getAccessToken();
        }
        else
        {
            if (!($tenantId = $this->getTenantId()))
            {
                Console::warn(
                    "Environment variable 'xero_tenant_id' should be set to one of the following GUIDs:",
                    $this->getTenantList($this->getConnections())
                );
                throw new RuntimeException("No tenant ID");
            }
            $accessToken = $this->getAccessToken();
        }

        $headers = new CurlerHeaders();
        $headers->setHeader(HttpHeader::AUTHORIZATION, "Bearer " . $accessToken);
        if ($tenantId)
        {
            $headers->setHeader("Xero-Tenant-Id", $tenantId);
        }

        return $headers;
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        $connections = $this->getConnections($ttl);

        Console::debug(
            sprintf("Connected to Xero with %s:",
                Convert::plural(count($connections), "tenant connection", null, true)),
            $this->getTenantList($connections)
        );

        return $this;
    }

    private function getConnections(int $ttl = 300): array
    {
        // See https://developer.xero.com/documentation/guides/oauth2/tenants
        return $this->getCurler("/connections", $ttl)->get();
    }

    private function getTenantList(array $connections): string
    {
        return implode("\n", array_map(
            fn($conn) => sprintf("- %s ~~(%s)~~", $conn["tenantId"], $conn["tenantName"]),
            $connections
        ));
    }

    private function getJwks(): array
    {
        $url = "https://identity.xero.com/.well-known/openid-configuration/jwks";
        return Cache::maybeGet(
            "jwks/" . $url,
            fn() => json_decode(file_get_contents($url), true),
            24 * 3600
        );
    }

    private function getVerifiedJwt(string $token): array
    {
        return (array)JWT::decode($token, JWK::parseKeySet($this->getJwks()));
    }

    private function getAccessToken(): string
    {
        while (
            !($token = Cache::get($this->TokenKey)) ||
            array_diff(
                self::OAUTH2_SCOPES,
                $this->getVerifiedJwt($token)["scope"]
            )
        ) {
            // If scopes have been added since the token was issued, reauthorize
            // interactively
            if ($token)
            {
                $this->authorize(false, true);
                continue;
            }
            $this->authorize();
        }

        return $token;
    }

    private function requireTenantId(): string
    {
        if ($tenantId = $this->getTenantId())
        {
            return $tenantId;
        }

        throw new RuntimeException("No tenant ID");
    }

    private function getTenantId(): ?string
    {
        // Flush the token cache to trigger [re-]authorization if a cached or
        // configured tenant ID isn't in the connections list
        $this->checkTenantAccess();

        if ($tenantId = Env::get("xero_tenant_id", null))
        {
            return $tenantId;
        }

        // If no tenant ID is configured, try to get one from the cache or via
        // authorization (e.g. if only one tenant is authorized)
        $i = 0;
        do
        {
            if ($tenantId = Cache::get($this->TenantIdKey) ?: null)
            {
                break;
            }

            $this->authorize();
        }
        while (!$i++);

        return $tenantId;
    }

    private function checkTenantAccess(bool $flush = true, bool $throw = false)
    {
        $tenantId = Env::get("xero_tenant_id", null) ?: Cache::get($this->TenantIdKey);
        if ($tenantId && empty(array_filter(
            $connections = $this->getConnections(),
            fn($conn) => !strcasecmp($conn["tenantId"], $tenantId)
        )))
        {
            Console::warn("Not connected to Xero tenant '$tenantId'; tenant connections:", $this->getTenantList($connections));
            if ($flush)
            {
                $this->flushToken();
            }
            if ($throw)
            {
                throw new RuntimeException("Invalid tenant ID: $tenantId");
            }
        }
    }

    public function authorize(bool $refresh = false, bool $reauthorize = false): void
    {
        if (!$reauthorize)
        {
            if (!$refresh && Cache::get($this->TokenKey))
            {
                return;
            }
            try
            {
                if ($this->refreshToken())
                {
                    return;
                }
            }
            catch (Throwable $ex)
            {
                Console::debug(
                    get_class($ex) . " thrown during refresh_token attempt:",
                    $ex->getMessage()
                );
            }
        }

        $this->flushToken();

        Console::info("Connecting to Xero");

        $authorizeUrl = $this->OAuth2Provider->getAuthorizationUrl([
            "scope" => implode(" ", self::OAUTH2_SCOPES)
        ]);
        $this->OAuth2State = $this->OAuth2Provider->getState();

        Console::debug("Starting HTTP server to receive OAuth 2.0 callbacks at",
            "{$this->OAuth2Listener->Address}:{$this->OAuth2Listener->Port}");

        $this->OAuth2Listener->start();

        try
        {
            // TODO: call xdg-open or similar here
            Console::log("Browse to the following URL to continue:",
                "\n$authorizeUrl");

            Console::info("Waiting for authorization");
            $code = $this->OAuth2Listener->listen(
                Closure::fromCallable([$this, "authorizeCallback"])
            );
        }
        finally
        {
            $this->OAuth2Listener->stop();
        }

        Console::log("Requesting an access token");
        $this->applyToken(
            $this->OAuth2Provider->getAccessToken(
                "authorization_code",
                ["code" => $code]
            )
        );
    }

    private function authorizeCallback(HttpRequest $request, bool & $continue, &$return): HttpResponse
    {
        if ($request->Method == "GET" &&
            ($url = parse_url($request->Target)) !== false &&
            ($url["path"] ?? null) == "/oauth2/callback")
        {
            parse_str($url["query"], $fields);
            if (($fields["state"] ?? null) == $this->OAuth2State &&
                $return = $fields["code"] ?? null)
            {
                Console::debug("Authorization code validated");
                return new HttpResponse("Authorization code received. You may now close this window.");
            }
        }
        Console::debug("Request did not provide a valid authorization code");
        $continue = true;
        return new HttpResponse("Invalid request. Please try again.", 400, "Bad Request");
    }

    private function applyToken(AccessTokenInterface $token)
    {
        $claims = $this->getVerifiedJwt($access = $token->getToken());
        Cache::set($this->TokenKey, $access, $token->getExpires() ?: $claims["exp"] ?? 0);

        if ($id = $token->getValues()["id_token"] ?? null)
        {
            // Xero's id_token expiry time is only 5 minutes, even though "one
            // of the purposes of the token is to improve user experience by
            // caching user information" (from Auth0 documentation), so ignore
            // any "exp" claims and cache it indefinitely
            Cache::set("{$this->TokenKey}/id", $id);
        }

        if ($refresh = $token->getRefreshToken())
        {
            Cache::set("{$this->TokenKey}/refresh", $refresh);
        }

        $authEventId = $this->getVerifiedJwt($token->getToken())["authentication_event_id"];
        $connections = $this->getConnections();

        // If a connection was "newly authorized in the current auth flow", or
        // only one connection exists, we can safely use its tenantId in lieu of
        // an explicit configuration
        if (($connection = array_filter($connections, fn($conn) => $conn["authEventId"] == $authEventId)) ||
            ($connection = count($connections) == 1 ? $connections : []))
        {
            Cache::set($this->TenantIdKey, reset($connection)["tenantId"]);
        }
        else
        {
            Cache::delete($this->TenantIdKey);
        }
        $this->checkTenantAccess(false, true);
    }

    private function refreshToken(): bool
    {
        if ($refreshToken = Cache::get("{$this->TokenKey}/refresh"))
        {
            $this->applyToken(
                $this->OAuth2Provider->getAccessToken(
                    "refresh_token",
                    ["refresh_token" => $refreshToken]
                )
            );
            return true;
        }
        return false;
    }

    private function getIdToken(): ?string
    {
        return Cache::get("{$this->TokenKey}/id") ?: null;
    }

    private function getIdClaims(): ?array
    {
        return ($jwt = $this->getIdToken())
            ? $this->getVerifiedJwt($jwt)
            : null;
    }

    private function flushToken()
    {
        Console::debug("Flushing OAuth token");
        Cache::delete($this->TokenKey);
        Cache::delete("{$this->TokenKey}/id");
        Cache::delete("{$this->TokenKey}/refresh");
        Cache::delete($this->TenantIdKey);
    }

    private function buildQuery(Context $ctx, array $fieldMap): array
    {
        $query = [
            "page" => 1
        ];

        $where = [];
        foreach ($fieldMap as $filterField => $field)
        {
            if (is_null($values = $ctx->claimFilterValue($_filterField = $filterField)) &&
                is_null($values = $ctx->claimFilterValue($_filterField = "!$filterField")))
            {
                continue;
            }

            [$prefix, $eq, $glue] = ($_filterField === $filterField
                ? ["", "==", " OR "]
                : ["NOT ", "!=", " AND "]);

            // TODO: escape each $value
            $where[$field] = array_map(
                function ($value) use ($field, $prefix, $eq)
                {
                    $expr = preg_replace(
                        ['/^\*([^*]*)\*$/', '/^([^*]*)\*$/', '/^\*([^*]*)$/'],
                        [
                            "{$prefix}{$field}.Contains(\"\$1\")",
                            "{$prefix}{$field}.StartsWith(\"\$1\")",
                            "{$prefix}{$field}.EndsWith(\"\$1\")",
                        ],
                        $value
                    );
                    if ($expr === $value)
                    {
                        return "{$field}{$eq}\"$value\"";
                    }

                    return $expr;
                },
                Convert::toArray($values)
            );
            $where[$field]["__glue"] = $glue;
        }
        if ($where)
        {
            $parts = [];
            foreach ($where as $group)
            {
                $glue = $group["__glue"];
                unset($group["__glue"]);
                $expr = implode($glue, $group);
                if (count($where) > 1 && count($group) > 1)
                {
                    $expr = "($expr)";
                }
                $parts[] = $expr;
            }
            $query["where"] = implode(" AND ", $parts);
        }

        if ($orderby = $ctx->claimFilterValue('$orderby'))
        {
            $parts = [];
            // Format: "<field_name>[ (ASC|DESC)][,...]"
            foreach (explode(",", $orderby) as $expr)
            {
                $expr = preg_split('/\h+/', trim($expr));
                if (count($expr) > 2)
                {
                    throw new UnexpectedValueException("Invalid \$orderby value '{$orderby}'");
                }
                if ($field = $fieldMap[$expr[0]] ?? null)
                {
                    $parts[] = $field . (strtoupper($expr[1] ?? "") == "DESC" ? " DESC" : "");
                }
            }
            if ($parts)
            {
                $query["order"] = implode(",", $parts);
            }
        }

        return $query;
    }

    private function generateInvoice(Invoice $invoice): array
    {
        $data                  = [];
        $data["Type"]          = "ACCREC";
        $data["Contact"]       = ["ContactID" => $invoice->Client->Id];
        $data["InvoiceNumber"] = $invoice->Number;
        $data["Reference"]     = $invoice->Reference;
        $data["Date"]          = $invoice->Date;
        $data["DueDate"]       = $invoice->DueDate;
        $data["LineItems"]     = [];
        $data["Status"]        = $invoice->Status;

        $data = array_filter($data, fn($value) => !is_null($value));

        foreach ($invoice->LineItems as $lineItem)
        {
            $line = [];
            $line["Description"] = $lineItem->Description;
            $line["Quantity"]    = $lineItem->Quantity;
            $line["UnitAmount"]  = $lineItem->UnitAmount;
            $line["ItemCode"]    = $lineItem->ItemCode;
            $line["AccountCode"] = $lineItem->AccountCode;
            $line["Tracking"]    = $lineItem->Tracking;

            $line = array_filter($line, fn($value) => !is_null($value));

            $data["LineItems"][] = $line;
        }

        return $data;
    }

    protected function getHttpDefinition(string $entity, HttpSyncDefinitionBuilder $define)
    {
        switch ($entity)
        {
            case Client::class:
                $plural = "Contacts";
                break;
            default:
                $plural = $entity::plural();
                break;
        }
        $define = ($define->path("/api.xro/2.0/" . $plural)
            ->query(
                fn(int $op, Context $ctx) => ($op == OP::READ_LIST
                    ? $this->buildQuery($ctx, self::QUERY_FIELD_MAPS[$entity])
                    : null)
            )->pagerCallback(
                fn() => new QueryPager(null, $plural)
            ));
        $pipeline = (Pipeline::create()
            ->throughKeyMap(self::SYNC_ENTITY_KEY_MAPS[$entity]));

        switch ($entity)
        {
            case Invoice::class:
                return $define->operations([OP::READ, OP::READ_LIST, OP::CREATE])
                    ->dataToEntityPipeline(
                        $pipeline->through(
                            fn(array $payload, Closure $next) => ($payload["Type"] != "ACCREC"
                                ? null
                                : $next($payload))
                        )->unless(
                            fn($result) => !is_null($result)
                        )
                    )->entityToDataPipeline(Pipeline::create()->through(
                        fn(Invoice $invoice, Closure $next) => $next($this->generateInvoice($invoice))
                    ));

            case Client::class:
                return $define->operations([OP::READ, OP::READ_LIST])
                    ->dataToEntityPipeline($pipeline);
        }

        return null;
    }

}
