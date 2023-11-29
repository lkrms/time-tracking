<?php declare(strict_types=1);

namespace Lkrms\Time\Sync\Provider\Xero;

use League\OAuth2\Client\Provider\GenericProvider;
use Lkrms\Concern\Immutable;
use Lkrms\Contract\IImmutable;
use Lkrms\Facade\Console;
use Lkrms\Http\Auth\AccessToken;
use Lkrms\Http\Auth\OAuth2Client;
use Lkrms\Http\Auth\OAuth2Flow;
use Lkrms\Http\Auth\OAuth2GrantType;
use Lkrms\Http\HttpServer;
use Lkrms\Utility\Arr;

final class XeroOAuth2Client extends OAuth2Client implements IImmutable
{
    use Immutable;

    private const MANDATORY_SCOPES = [
        'openid',
        'profile',
        'email',
        'offline_access',
    ];

    /**
     * @var string[]
     */
    private array $DefaultScopes = self::MANDATORY_SCOPES;

    /**
     * @var (callable(AccessToken, array<string,mixed>|null $idToken, OAuth2GrantType::*): mixed)|null
     */
    private $Callback = null;

    /**
     * @param string[] $scopes
     * @return static
     */
    public function withDefaultScopes(array $scopes)
    {
        return $this->withPropertyValue(
            'DefaultScopes',
            Arr::extend(self::MANDATORY_SCOPES, ...$scopes)
        );
    }

    /**
     * @param (callable(AccessToken, array<string,mixed>|null $idToken, OAuth2GrantType::*): mixed)|null $callback
     * @return static
     */
    public function withCallback(?callable $callback)
    {
        return $this->withPropertyValue('Callback', $callback);
    }

    /**
     * @inheritDoc
     */
    protected function getListener(): ?HttpServer
    {
        $listener = new HttpServer(
            $this->Env->get('app_host', 'localhost'),
            $this->Env->getInt('app_port', 27755),
        );

        $proxyHost = $this->Env->getNullable('app_proxy_host', null);
        $proxyPort = $this->Env->getNullableInt('app_proxy_port', null);

        if ($proxyHost !== null && $proxyPort !== null) {
            return $listener->withProxy(
                $proxyHost,
                $proxyPort,
                $this->Env->getNullableBool('app_proxy_tls', null),
                $this->Env->getNullable('app_proxy_base_path', null),
            );
        }

        return $listener;
    }

    /**
     * @inheritDoc
     *
     * @link https://developer.xero.com/documentation/oauth2/scopes
     */
    protected function getProvider(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => $this->Env->get('xero_app_client_id'),
            'clientSecret' => $this->Env->get('xero_app_client_secret'),
            'redirectUri' => $this->getRedirectUri(),
            'urlAuthorize' => 'https://login.xero.com/identity/connect/authorize',
            'urlAccessToken' => 'https://identity.xero.com/connect/token',
            'urlResourceOwnerDetails' => 'https://identity.xero.com/connect/userinfo',
            'scopes' => $this->DefaultScopes,
            'scopeSeparator' => ' ',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function getFlow(): int
    {
        return OAuth2Flow::AUTHORIZATION_CODE;
    }

    /**
     * @inheritDoc
     */
    protected function getJsonWebKeySetUrl(): ?string
    {
        return 'https://identity.xero.com/.well-known/openid-configuration/jwks';
    }

    /**
     * @inheritDoc
     */
    protected function receiveToken(AccessToken $token, ?array $idToken, string $grantType): void
    {
        Console::debug('Xero access token received');

        if ($this->Callback !== null) {
            ($this->Callback)($token, $idToken, $grantType);
        }
    }
}