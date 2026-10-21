<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use Mattdi\Fayda\Core\Contracts\StateStore;
use Mattdi\Fayda\Core\Dto\AuthorizationRequest;
use Mattdi\Fayda\Core\Exceptions\ConfigurationException;

final class AuthorizationUrlBuilder
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly StateStore $states,
        private readonly string $authorizationEndpoint,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $context
     */
    public function build(array $overrides = [], array $context = []): AuthorizationRequest
    {
        $clientId = $this->clientId();
        $redirectUri = $this->redirectUri();

        $state = isset($overrides['state']) && is_string($overrides['state']) && $overrides['state'] !== ''
            ? $overrides['state']
            : Pkce::randomState();

        $nonce = isset($overrides['nonce']) && is_string($overrides['nonce']) && $overrides['nonce'] !== ''
            ? $overrides['nonce']
            : Pkce::randomNonce();

        $pkceEnabled = (bool) ($this->config['pkce'] ?? true);

        $codeVerifier = null;
        if ($pkceEnabled) {
            $codeVerifier = isset($overrides['code_verifier']) && is_string($overrides['code_verifier'])
                ? $overrides['code_verifier']
                : Pkce::verifier();
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => (string) ($this->config['response_type'] ?? 'code'),
            'scope' => implode(' ', $this->listValue($overrides['scopes'] ?? $this->config['scopes'] ?? ['openid'])),
            'state' => $state,
            'nonce' => $nonce,
        ];

        $acrValues = $this->listValue($overrides['acr_values'] ?? $this->config['acr_values'] ?? []);
        if ($acrValues !== []) {
            $params['acr_values'] = implode(' ', $acrValues);
        }

        $claims = $overrides['claims'] ?? $this->config['claims'] ?? null;
        if ($claims !== null && $claims !== '' && $claims !== []) {
            $params['claims'] = is_string($claims)
                ? $claims
                : (string) json_encode($this->normalizeClaims($claims), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }

        foreach (['ui_locales', 'display', 'response_mode', 'prompt', 'login_hint', 'max_age'] as $key) {
            $value = $overrides[$key] ?? $this->config[$key] ?? null;

            if ($value !== null && $value !== '' && $value !== []) {
                $params[$key] = is_array($value) ? implode(' ', $value) : (string) $value;
            }
        }

        if ($codeVerifier !== null) {
            $params['code_challenge'] = Pkce::challenge($codeVerifier);
            $params['code_challenge_method'] = 'S256';
        }

        $extra = $this->config['extra_params'] ?? [];
        if (is_array($extra)) {
            $params = array_merge($params, array_map(static fn (mixed $value): string => (string) $value, $extra));
        }

        $overrideExtra = $overrides['extra'] ?? [];
        if (is_array($overrideExtra)) {
            $params = array_merge($params, array_map(static fn (mixed $value): string => (string) $value, $overrideExtra));
        }

        $ttl = (int) ($this->config['state_ttl'] ?? 600);
        if ($ttl < 60) {
            $ttl = 60;
        }

        $this->states->put($state, [
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'redirect_uri' => $redirectUri,
            'context' => $context,
        ], $ttl);

        return new AuthorizationRequest(
            url: $this->appendQuery($this->authorizationEndpoint, $params),
            state: $state,
            nonce: $nonce,
        );
    }

    private function clientId(): string
    {
        $clientId = $this->config['client']['id'] ?? null;

        if (! is_string($clientId) || $clientId === '') {
            throw new ConfigurationException('The Fayda client id is not configured.');
        }

        return $clientId;
    }

    private function redirectUri(): string
    {
        $redirectUri = $this->config['redirect_uri'] ?? null;

        if (! is_string($redirectUri) || $redirectUri === '') {
            throw new ConfigurationException('The Fayda redirect URI is not configured.');
        }

        return $redirectUri;
    }

    /**
     * @return list<string>
     */
    private function listValue(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value)) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }

    private function normalizeClaims(mixed $claims): mixed
    {
        if (! is_array($claims)) {
            return $claims;
        }

        if ($claims === []) {
            return new \stdClass;
        }

        foreach ($claims as $key => $value) {
            $claims[$key] = $this->normalizeClaims($value);
        }

        return $claims;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function appendQuery(string $endpoint, array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if ($query === '') {
            return $endpoint;
        }

        return $endpoint.(str_contains($endpoint, '?') ? '&' : '?').$query;
    }
}
