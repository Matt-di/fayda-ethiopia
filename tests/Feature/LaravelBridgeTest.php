<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Event;
use Mattdi\Fayda\FaydaClient;
use Mattdi\Fayda\Laravel\Events\FaydaLoginSucceeded;
use Mattdi\Fayda\Laravel\Facades\Fayda;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;
use Mattdi\Fayda\Tests\Support\InMemoryStateStore;

it('redirects to the Fayda authorization endpoint', function (): void {
    $response = $this->get('/auth/fayda/redirect');

    $response->assertRedirect();

    $location = (string) $response->headers->get('Location');
    $query = faydaQuery($location);

    expect($location)->toStartWith('https://idp.test/authorize')
        ->and($query['client_id'])->toBe('client-1')
        ->and($query['redirect_uri'])->toBe('https://app.test/auth/fayda/callback')
        ->and($query['response_type'])->toBe('code')
        ->and($query['code_challenge_method'])->toBe('S256')
        ->and($query['state'])->not->toBeEmpty()
        ->and($query['nonce'])->not->toBeEmpty();
});

it('resolves the client through the facade', function (): void {
    $url = Fayda::authorizationUrl();

    expect($url)->toContain('https://idp.test/authorize')
        ->and(faydaQuery($url)['client_id'])->toBe('client-1');
});

it('returns a validation error for an invalid state', function (): void {
    $response = $this->get('/auth/fayda/callback?code=code-1&state=bogus');

    $response->assertRedirect('/');
    $response->assertSessionHasErrors('fayda');
});

it('completes the callback through the registered routes', function (): void {
    Event::fake([FaydaLoginSucceeded::class]);

    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $factory = new HttpFactory;

    $client = new FaydaClient(
        config: faydaConfig([
            'issuer' => 'https://idp.test',
            'endpoints' => [
                'authorization' => 'https://idp.test/authorize',
                'token' => 'https://idp.test/token',
                'userinfo' => 'https://idp.test/userinfo',
                'jwks' => 'https://idp.test/jwks',
            ],
            'client' => [
                'id' => 'client-1',
                'private_key_path' => __DIR__.'/../Fixtures/private-key.pem',
                'key_id' => 'test-key',
            ],
            'redirect_uri' => 'https://app.test/auth/fayda/callback',
        ]),
        http: $http,
        states: $store,
        requestFactory: $factory,
        streamFactory: $factory,
    );

    $this->app->instance(FaydaClient::class, $client);
    $this->app->instance('fayda', $client);

    $redirect = $this->get('/auth/fayda/redirect');
    $state = faydaQuery((string) $redirect->headers->get('Location'))['state'];
    $nonce = $store->states[$state]['nonce'];

    $http->queue = [
        faydaJsonResponse([
            'access_token' => 'access-1',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'id_token' => faydaSign(faydaIdTokenClaims($nonce)),
        ]),
        faydaJwksResponse(),
        faydaJwtResponse(faydaSign(faydaUserInfoClaims())),
    ];

    $response = $this->get('/auth/fayda/callback?code=code-1&state='.$state);

    $response->assertRedirect('/');
    $response->assertSessionHasNoErrors();

    Event::assertDispatched(FaydaLoginSucceeded::class, function (FaydaLoginSucceeded $event): bool {
        return $event->user()->sub === 'fayda-subject-1'
            && $event->user()->name === 'Abebe Bikila';
    });
});

it('accepts a form_post callback', function (): void {
    $response = $this->post('/auth/fayda/callback', [
        'code' => 'code-1',
        'state' => 'bogus',
    ]);

    $response->assertRedirect('/');
    $response->assertSessionHasErrors('fayda');
});
