<?php

declare(strict_types=1);

namespace Lkrms\Time;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Lkrms\Console\Console;
use Lkrms\Curler\CurlerHeaders;
use Lkrms\Store\Cache;
use Lkrms\Support\HttpRequest;
use Lkrms\Support\HttpResponse;
use Lkrms\Support\HttpServer;
use Lkrms\Sync\Provider\HttpSyncProvider;
use Lkrms\Util\Env;
use RuntimeException;
use Throwable;

class XeroProvider extends HttpSyncProvider
{
    /**
     * @var string[]
     * @link https://developer.xero.com/documentation/oauth2/scopes
     */
    private const OAUTH2_SCOPES = [
        "openid",
        "email",
        "profile",
        "offline_access",
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

    /**
     * @var array|null
     */
    private $Connections;

    public function __construct()
    {
        $host = Env::get("app_host", "localhost");
        $port = (int)Env::get("app_port", "27755");

        $this->OAuth2Provider = new GenericProvider([
            "clientId"                => Env::get("xero_app_client_id"),
            "clientSecret"            => Env::get("xero_app_client_secret"),
            "redirectUri"             => "http://$host:$port/oauth2/callback",
            "urlAuthorize"            => "https://login.xero.com/identity/connect/authorize",
            "urlAccessToken"          => "https://identity.xero.com/connect/token",
            "urlResourceOwnerDetails" => null,
        ]);

        $this->OAuth2Listener = new HttpServer($host, $port);

        $this->TokenKey    = "token/" . $this->getBaseUrl();
        $this->TenantIdKey = "uuid/" . $this->getBaseUrl() . "/tenant";
    }

    protected function getBackendIdentifier(): array
    {
        return [$this->requireTenantId()];
    }

    protected function getBaseUrl(string $path = null): string
    {
        return "https://api.xero.com";
    }

    protected function getHeaders(?string $path): ?CurlerHeaders
    {
        $headers = new CurlerHeaders();
        $headers->setHeader("Authorization", "Bearer " . $this->getAccessToken());

        if ($path != "/connections")
        {
            if (!($tenantId = $this->getTenantId()))
            {
                Console::warn(
                    "Set environment variable 'xero_tenant_id' to one of the following GUIDs:",
                    implode("\n", array_map(
                        fn($conn) => sprintf("- %s ~~(%s)~~", $conn["tenantId"], $conn["tenantName"]),
                        $this->Connections
                    ))
                );
                throw new RuntimeException("No tenant ID");
            }
            $headers->setHeader("Xero-Tenant-Id", $tenantId);
        }

        return $headers;
    }

    private function getJwks(): array
    {
        return Cache::maybeGet(
            "jwks/" . $this->getBaseUrl(),
            fn() => json_decode(file_get_contents(
                "https://identity.xero.com/.well-known/openid-configuration/jwks"
            ), true),
            24 * 3600
        );
    }

    private function getVerifiedJwt(string $token): array
    {
        return (array)JWT::decode($token, JWK::parseKeySet($this->getJwks()));
    }

    private function getAccessToken(): string
    {
        while (!($token = Cache::get($this->TokenKey)))
        {
            $this->authorize();
        }

        return $token;
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
        while (!($tenantId = Env::get("xero_tenant_id", null) ?: Cache::get($this->TenantIdKey)))
        {
            if ($i ?? 0)
            {
                return null;
            }
            $this->authorize(false, true);
            $i = 1;
        }
        return $tenantId;
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

    private function authorizeCallback(
        HttpRequest $request,
        bool & $continue,
        &$return
    ): HttpResponse
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
        $connections = $this->Connections = $this->getCurler("/connections")->getJson();

        // If a connection was "newly authorized in the current auth flow", or
        // only one connection exists, we can safely use its tenantId in lieu of
        // an explicit configuration
        if (($connection = array_filter($connections, fn($conn) => $conn["authEventId"] == $authEventId)) ||
            ($connection = count($connections) == 1 ? $connections : []))
        {
            Cache::set($this->TenantIdKey, $connection[0]["tenantId"]);
        }
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

    private function flushToken()
    {
        Cache::delete($this->TokenKey);
        Cache::delete("{$this->TokenKey}/id");
        Cache::delete("{$this->TokenKey}/refresh");
        Cache::delete($this->TenantIdKey);
    }

}