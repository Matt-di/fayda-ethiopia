<?php

declare(strict_types=1);

namespace Mattdi\Fayda;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Mattdi\Fayda\Core\AuthorizationUrlBuilder;
use Mattdi\Fayda\Core\ClientAssertion;
use Mattdi\Fayda\Core\Contracts\StateStore;
use Mattdi\Fayda\Core\Discovery;
use Mattdi\Fayda\Core\Dto\AuthorizationRequest;
use Mattdi\Fayda\Core\Dto\FaydaUser;
use Mattdi\Fayda\Core\Dto\LoginResult;
use Mattdi\Fayda\Core\Dto\TokenResponse;
use Mattdi\Fayda\Core\Exceptions\AuthenticationFailedException;
use Mattdi\Fayda\Core\Exceptions\ConfigurationException;
use Mattdi\Fayda\Core\Exceptions\InvalidStateException;
use Mattdi\Fayda\Core\Exceptions\InvalidTokenException;
use Mattdi\Fayda\Core\JwksProvider;
use Mattdi\Fayda\Core\JwtVerifier;
use Mattdi\Fayda\Core\State\NativeSessionStateStore;
use Mattdi\Fayda\Core\TokenClient;
use Mattdi\Fayda\Core\UserInfoClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Framework-agnostic Fayda (eSignet) OpenID Connect client.
 */
final class FaydaClient
{
    private StateStore $states;

    /** @var array{authorization: string, token: string, userinfo: string, jwks: string}|null */
    private ?array $resolvedEndpoints = null;

    private ?JwksProvider $jwksProvider = null;

    private ?JwtVerifier $jwtVerifier = null;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly ClientInterface $http,
        private readonly ?CacheInterface $cache = null,
        ?StateStore $states = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->states = $states ?? new NativeSessionStateStore;

        $fallbackFactory = new HttpFactory;
        $this->requestFactory = $requestFactory ?? $fallbackFactory;
        $this->streamFactory = $streamFactory ?? $fallbackFactory;
    }

    /**
     * Convenience factory for plain PHP applications.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(
        array $config,
        ?ClientInterface $http = null,
        ?CacheInterface $cache = null,
        ?StateStore $states = null,
    ): self {
        $http ??= new GuzzleClient([
            'timeout' => (float) ($config['http']['timeout'] ?? 15),
            'connect_timeout' => (float) ($config['http']['connect_timeout'] ?? 5),
        ]);

        return new self($config, $http, $cache, $states);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $context
     */
    public function authorizationUrl(array $overrides = [], array $context = []): string
    {
        return $this->authorizationRequest($overrides, $context)->url;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $context
     */
    public function authorizationRequest(array $overrides = [], array $context = []): AuthorizationRequest
    {
        return (new AuthorizationUrlBuilder(
            $this->config,
            $this->states,
            $this->endpoints()['authorization'],
        ))->build($overrides, $context);
    }

    /**
     * Complete the authorization code flow and return the verified user.
     */
    public function handleCallback(
        string $code,
        ?string $state = null,
        ?string $error = null,
        ?string $errorDescription = null,
    ): FaydaUser {
        return $this->authenticate($code, $state, $error, $errorDescription)->user;
    }

    /**
     * Complete the authorization code flow and return the user plus tokens.
     */
    public function authenticate(
        string $code,
        ?string $state = null,
        ?string $error = null,
        ?string $errorDescription = null,
    ): LoginResult {
        if ($error !== null && $error !== '') {
            if ($state !== null && $state !== '') {
                $this->states->pull($state);
            }

            throw AuthenticationFailedException::fromIdp($error, $errorDescription);
        }

        if ($code === '') {
            throw new AuthenticationFailedException('The authorization code is missing from the callback.');
        }

        if ($state === null || $state === '') {
            throw new InvalidStateException('The state parameter is missing from the callback.');
        }

        $stored = $this->states->pull($state);

        if ($stored === null) {
            throw new InvalidStateException('The state parameter is invalid or has expired.');
        }

        $codeVerifier = isset($stored['code_verifier']) && is_string($stored['code_verifier'])
            ? $stored['code_verifier']
            : null;

        $redirectUri = isset($stored['redirect_uri']) && is_string($stored['redirect_uri']) && $stored['redirect_uri'] !== ''
            ? $stored['redirect_uri']
            : $this->redirectUri();

        $nonce = isset($stored['nonce']) && is_string($stored['nonce']) ? $stored['nonce'] : null;

        $token = $this->tokenClient()->exchangeCode($code, $redirectUri, $codeVerifier);

        $idTokenClaims = [];

        if ($token->idToken !== null) {
            $idTokenClaims = $this->jwtVerifier()->verify($token->idToken, [
                'nonce' => $nonce,
            ]);
        } elseif ($this->requiresIdToken()) {
            throw InvalidTokenException::missingIdToken();
        }

        $userInfoClaims = [];

        if ((bool) ($this->config['fetch_userinfo'] ?? true)) {
            $userInfoClaims = $this->userInfoClient()->fetch($token->accessToken);
        }

        if (
            isset($idTokenClaims['sub'], $userInfoClaims['sub'])
            && is_string($idTokenClaims['sub'])
            && is_string($userInfoClaims['sub'])
            && ! hash_equals($idTokenClaims['sub'], $userInfoClaims['sub'])
        ) {
            throw InvalidTokenException::claim('The "sub" claim in the userinfo response does not match the id_token.');
        }

        $claims = array_replace($idTokenClaims, $userInfoClaims);

        if (! isset($claims['sub']) || ! is_string($claims['sub']) || $claims['sub'] === '') {
            throw InvalidTokenException::claim('The Fayda response did not contain a subject identifier.');
        }

        return new LoginResult(
            user: FaydaUser::fromClaims($claims),
            token: $token,
            idTokenClaims: $idTokenClaims,
            userInfoClaims: $userInfoClaims,
        );
    }

    public function refresh(string $refreshToken): TokenResponse
    {
        return $this->tokenClient()->refresh($refreshToken);
    }

    /**
     * @return array{authorization: string, token: string, userinfo: string, jwks: string}
     */
    public function endpoints(): array
    {
        if ($this->resolvedEndpoints !== null) {
            return $this->resolvedEndpoints;
        }

        $configured = $this->config['endpoints'] ?? [];
        $configured = is_array($configured) ? $configured : [];

        $names = ['authorization', 'token', 'userinfo', 'jwks'];
        $resolved = [];

        foreach ($names as $name) {
            $value = $configured[$name] ?? null;
            $resolved[$name] = is_string($value) && $value !== '' ? $value : null;
        }

        if (in_array(null, $resolved, true)) {
            $discovery = $this->config['discovery'] ?? [];
            $discoveryEnabled = is_array($discovery) ? (bool) ($discovery['enabled'] ?? true) : (bool) $discovery;
            $discoveryTtl = is_array($discovery) ? (int) ($discovery['cache_ttl'] ?? 3600) : 3600;

            if (! $discoveryEnabled) {
                $missing = array_keys(array_filter($resolved, static fn (?string $value): bool => $value === null));
                throw new ConfigurationException(
                    'The following Fayda endpoints are not configured: '.implode(', ', $missing).'.'
                );
            }

            $issuer = $this->issuer();

            if ($issuer === null) {
                throw new ConfigurationException(
                    'Set the Fayda issuer or provide every endpoint explicitly in fayda.endpoints.'
                );
            }

            $discovered = (new Discovery(
                $issuer,
                $this->http,
                $this->cache,
                $discoveryTtl,
                $this->requestFactory,
            ))->endpoints();

            foreach ($names as $name) {
                $resolved[$name] ??= $discovered[$name];
            }
        }

        /** @var array{authorization: string, token: string, userinfo: string, jwks: string} $resolved */
        return $this->resolvedEndpoints = $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return $this->config;
    }

    private function tokenClient(): TokenClient
    {
        return new TokenClient(
            $this->endpoints()['token'],
            $this->clientId(),
            $this->assertionType(),
            new ClientAssertion($this->config),
            $this->http,
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    private function userInfoClient(): UserInfoClient
    {
        return new UserInfoClient(
            $this->endpoints()['userinfo'],
            $this->http,
            $this->jwtVerifier(),
            $this->issuer(),
            $this->clientId(),
            $this->requestFactory,
        );
    }

    private function jwtVerifier(): JwtVerifier
    {
        if ($this->jwtVerifier !== null) {
            return $this->jwtVerifier;
        }

        $this->jwksProvider ??= new JwksProvider(
            $this->endpoints()['jwks'],
            $this->http,
            $this->cache,
            (int) ($this->config['jwks_cache_ttl'] ?? 3600),
            $this->requestFactory,
        );

        return $this->jwtVerifier = new JwtVerifier(
            $this->jwksProvider,
            $this->issuer(),
            $this->clientId(),
            (int) ($this->config['clock_skew'] ?? 60),
        );
    }

    private function requiresIdToken(): bool
    {
        if (array_key_exists('require_id_token', $this->config)) {
            return (bool) $this->config['require_id_token'];
        }

        $scopes = $this->config['scopes'] ?? ['openid'];

        if (is_string($scopes)) {
            $scopes = preg_split('/\s+/', trim($scopes)) ?: [];
        }

        return is_array($scopes) && in_array('openid', $scopes, true);
    }

    private function clientId(): string
    {
        $clientId = $this->config['client']['id'] ?? null;

        if (! is_string($clientId) || $clientId === '') {
            throw new ConfigurationException('The Fayda client id is not configured.');
        }

        return $clientId;
    }

    private function assertionType(): string
    {
        $type = $this->config['client']['assertion_type'] ?? 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';

        if (! is_string($type) || $type === '') {
            throw new ConfigurationException('The Fayda client assertion type is not configured.');
        }

        return $type;
    }

    private function redirectUri(): string
    {
        $redirectUri = $this->config['redirect_uri'] ?? null;

        if (! is_string($redirectUri) || $redirectUri === '') {
            throw new ConfigurationException('The Fayda redirect URI is not configured.');
        }

        return $redirectUri;
    }

    private function issuer(): ?string
    {
        $issuer = $this->config['issuer'] ?? null;

        return is_string($issuer) && $issuer !== '' ? rtrim($issuer, '/') : null;
    }
}
