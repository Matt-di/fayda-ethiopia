<?php

declare(strict_types=1);

use Mattdi\Fayda\Core\AuthorizationUrlBuilder;
use Mattdi\Fayda\Core\Exceptions\ConfigurationException;
use Mattdi\Fayda\Core\Pkce;
use Mattdi\Fayda\Tests\Support\InMemoryStateStore;

it('builds an authorization URL with PKCE, state, nonce, claims and ACR values', function (): void {
    $store = new InMemoryStateStore;
    $builder = new AuthorizationUrlBuilder(
        faydaConfig([
            'claims' => [
                'userinfo' => ['name' => ['essential' => true]],
                'id_token' => [],
            ],
            'ui_locales' => 'en',
            'display' => 'page',
        ]),
        $store,
        'https://idp.test/authorize',
    );

    $request = $builder->build();
    $query = faydaQuery($request->url);

    expect($query['client_id'])->toBe('client-1')
        ->and($query['redirect_uri'])->toBe('https://app.test/auth/fayda/callback')
        ->and($query['response_type'])->toBe('code')
        ->and($query['scope'])->toBe('openid profile email')
        ->and($query['acr_values'])->toBe('mosip:idp:acr:generated-code')
        ->and($query['state'])->toBe($request->state)
        ->and($query['nonce'])->toBe($request->nonce)
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['ui_locales'])->toBe('en')
        ->and($query['display'])->toBe('page')
        ->and($query['claims'])->toContain('"id_token":{}');

    $stored = $store->states[$request->state];

    expect($stored['nonce'])->toBe($request->nonce)
        ->and($stored['code_verifier'])->toBeString()
        ->and(Pkce::challenge($stored['code_verifier']))->toBe($query['code_challenge']);
});

it('omits PKCE parameters when PKCE is disabled', function (): void {
    $store = new InMemoryStateStore;
    $builder = new AuthorizationUrlBuilder(
        faydaConfig(['pkce' => false]),
        $store,
        'https://idp.test/authorize',
    );

    $request = $builder->build();
    $query = faydaQuery($request->url);

    expect($query)->not->toHaveKey('code_challenge')
        ->and($query)->not->toHaveKey('code_challenge_method')
        ->and($store->states[$request->state]['code_verifier'])->toBeNull();
});

it('supports per-request overrides', function (): void {
    $store = new InMemoryStateStore;
    $builder = new AuthorizationUrlBuilder(faydaConfig(), $store, 'https://idp.test/authorize');

    $request = $builder->build([
        'acr_values' => ['mosip:idp:acr:biometrics'],
        'prompt' => 'login',
        'extra' => ['foo' => 'bar'],
    ]);

    $query = faydaQuery($request->url);

    expect($query['acr_values'])->toBe('mosip:idp:acr:biometrics')
        ->and($query['prompt'])->toBe('login')
        ->and($query['foo'])->toBe('bar');
});

it('fails when the client id is missing', function (): void {
    $builder = new AuthorizationUrlBuilder(
        faydaConfig(['client' => ['id' => null]]),
        new InMemoryStateStore,
        'https://idp.test/authorize',
    );

    $builder->build();
})->throws(ConfigurationException::class);
