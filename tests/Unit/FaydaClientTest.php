<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use Mattdi\Fayda\Core\Exceptions\AuthenticationFailedException;
use Mattdi\Fayda\Core\Exceptions\ConfigurationException;
use Mattdi\Fayda\Core\Exceptions\InvalidStateException;
use Mattdi\Fayda\Core\Exceptions\InvalidTokenException;
use Mattdi\Fayda\FaydaClient;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;
use Mattdi\Fayda\Tests\Support\InMemoryStateStore;

function faydaFlowClient(
    FakeHttpClient $http,
    InMemoryStateStore $store,
    array $config = [],
): FaydaClient {
    $factory = new HttpFactory;

    return new FaydaClient(
        config: faydaConfig($config),
        http: $http,
        cache: null,
        states: $store,
        requestFactory: $factory,
        streamFactory: $factory,
    );
}

it('completes the authorization code flow and verifies both tokens', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store);

    $request = $client->authorizationRequest();
    $nonce = $store->states[$request->state]['nonce'];
    $verifier = $store->states[$request->state]['code_verifier'];

    $idToken = faydaSign(faydaIdTokenClaims($nonce, [
        'at_hash' => faydaAccessTokenHash('access-1'),
    ]));
    $userInfo = faydaSign(faydaUserInfoClaims());

    $http->queue = [
        faydaJsonResponse([
            'access_token' => 'access-1',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'id_token' => $idToken,
            'refresh_token' => 'refresh-1',
        ]),
        faydaJwksResponse(),
        faydaJwtResponse($userInfo),
    ];

    $result = $client->authenticate('code-1', $request->state, iss: 'https://idp.test');

    expect($result->user->sub)->toBe('fayda-subject-1')
        ->and($result->user->name)->toBe('Abebe Bikila')
        ->and($result->user->phoneNumber)->toBe('+251911000000')
        ->and($result->token->accessToken)->toBe('access-1')
        ->and($result->token->refreshToken)->toBe('refresh-1')
        ->and($result->idTokenClaims['nonce'])->toBe($nonce)
        ->and($result->userInfoClaims['name'])->toBe('Abebe Bikila');

    $tokenRequest = (string) $http->requests[0]->getBody();
    parse_str($tokenRequest, $params);

    expect($params['code'])->toBe('code-1')
        ->and($params['code_verifier'])->toBe($verifier);
});

it('rejects an at_hash claim that does not match the access token', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store);

    $request = $client->authorizationRequest();
    $nonce = $store->states[$request->state]['nonce'];

    $http->queue = [
        faydaJsonResponse([
            'access_token' => 'access-1',
            'id_token' => faydaSign(faydaIdTokenClaims($nonce, [
                'at_hash' => faydaAccessTokenHash('different-access-token'),
            ])),
        ]),
        faydaJwksResponse(),
    ];

    $client->authenticate('code-1', $request->state);
})->throws(InvalidTokenException::class, 'at_hash');

it('rejects an authorization response issuer that does not match the configured issuer', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store);

    $request = $client->authorizationRequest();

    $client->authenticate('code-1', $request->state, iss: 'https://other-idp.test');
})->throws(AuthenticationFailedException::class, 'issuer');

it('rejects a replayed state value', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store);

    $request = $client->authorizationRequest();
    $nonce = $store->states[$request->state]['nonce'];
    $idToken = faydaSign(faydaIdTokenClaims($nonce));

    $http->queue = [
        faydaJsonResponse(['access_token' => 'access-1', 'id_token' => $idToken]),
        faydaJwksResponse(),
        faydaJwtResponse(faydaSign(faydaUserInfoClaims())),
    ];

    $client->authenticate('code-1', $request->state);

    $client->authenticate('code-1', $request->state);
})->throws(InvalidStateException::class);

it('rejects a userinfo subject that differs from the id_token', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store);

    $request = $client->authorizationRequest();
    $nonce = $store->states[$request->state]['nonce'];

    $http->queue = [
        faydaJsonResponse([
            'access_token' => 'access-1',
            'id_token' => faydaSign(faydaIdTokenClaims($nonce)),
        ]),
        faydaJwksResponse(),
        faydaJwtResponse(faydaSign(faydaUserInfoClaims(['sub' => 'someone-else']))),
    ];

    $client->authenticate('code-1', $request->state);
})->throws(InvalidTokenException::class, 'userinfo');

it('requires an id_token when the openid scope is requested', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store);

    $request = $client->authorizationRequest();

    $http->queue = [
        faydaJsonResponse(['access_token' => 'access-1']),
    ];

    $client->authenticate('code-1', $request->state);
})->throws(InvalidTokenException::class, 'id_token');

it('uses id_token claims when userinfo is disabled', function (): void {
    $store = new InMemoryStateStore;
    $http = new FakeHttpClient([]);
    $client = faydaFlowClient($http, $store, ['fetch_userinfo' => false]);

    $request = $client->authorizationRequest();
    $nonce = $store->states[$request->state]['nonce'];

    $http->queue = [
        faydaJsonResponse([
            'access_token' => 'access-1',
            'id_token' => faydaSign(faydaIdTokenClaims($nonce, ['name' => 'From ID Token'])),
        ]),
        faydaJwksResponse(),
    ];

    $result = $client->authenticate('code-1', $request->state);

    expect($result->user->name)->toBe('From ID Token')
        ->and($result->userInfoClaims)->toBe([]);
});

it('fails when the token endpoint is not configured and discovery is disabled', function (): void {
    $config = faydaConfig([
        'discovery' => ['enabled' => false],
        'endpoints' => ['token' => null],
    ]);

    $client = new FaydaClient($config, new FakeHttpClient([]), null, new InMemoryStateStore);

    $client->endpoints();
})->throws(ConfigurationException::class, 'token');
