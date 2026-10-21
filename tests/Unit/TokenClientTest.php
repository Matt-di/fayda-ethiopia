<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Mattdi\Fayda\Core\ClientAssertion;
use Mattdi\Fayda\Core\Exceptions\TokenRequestException;
use Mattdi\Fayda\Core\TokenClient;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;

function faydaTokenClient(FakeHttpClient $http, array $config = []): TokenClient
{
    $config = faydaConfig($config);
    $factory = new HttpFactory;

    return new TokenClient(
        'https://idp.test/token',
        'client-1',
        'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
        new ClientAssertion($config),
        $http,
        $factory,
        $factory,
    );
}

it('exchanges an authorization code using private_key_jwt and PKCE', function (): void {
    $http = new FakeHttpClient([
        faydaJsonResponse([
            'access_token' => 'access-1',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'id_token' => 'id-1',
            'refresh_token' => 'refresh-1',
        ]),
    ]);

    $token = faydaTokenClient($http)->exchangeCode('code-1', 'https://app.test/cb', 'verifier-1');
    $body = (string) $http->requests[0]->getBody();
    parse_str($body, $params);

    expect($token->accessToken)->toBe('access-1')
        ->and($token->idToken)->toBe('id-1')
        ->and($token->refreshToken)->toBe('refresh-1')
        ->and($token->expiresIn)->toBe(3600)
        ->and($params['grant_type'])->toBe('authorization_code')
        ->and($params['code'])->toBe('code-1')
        ->and($params['redirect_uri'])->toBe('https://app.test/cb')
        ->and($params['code_verifier'])->toBe('verifier-1')
        ->and($params['client_id'])->toBe('client-1')
        ->and($params['client_assertion_type'])->toBe('urn:ietf:params:oauth:client-assertion-type:jwt-bearer')
        ->and($params['client_assertion'])->toBeString()
        ->and(substr_count($params['client_assertion'], '.'))->toBe(2);
});

it('throws a typed exception for an OAuth error response', function (): void {
    $http = new FakeHttpClient([
        faydaJsonResponse([
            'error' => 'invalid_grant',
            'error_description' => 'The authorization code has expired.',
        ], 400),
    ]);

    try {
        faydaTokenClient($http)->exchangeCode('code-1', 'https://app.test/cb');
    } catch (TokenRequestException $e) {
        expect($e->error())->toBe('invalid_grant')
            ->and($e->errorDescription())->toContain('expired')
            ->and($e->statusCode())->toBe(400);

        return;
    }

    $this->fail('Expected a TokenRequestException.');
});

it('throws for an invalid JSON response', function (): void {
    $http = new FakeHttpClient([
        new Response(200, ['Content-Type' => 'text/plain'], 'not-json'),
    ]);

    faydaTokenClient($http)->exchangeCode('code-1', 'https://app.test/cb');
})->throws(TokenRequestException::class);

it('refreshes an access token', function (): void {
    $http = new FakeHttpClient([
        faydaJsonResponse(['access_token' => 'access-2', 'expires_in' => 3600]),
    ]);

    $token = faydaTokenClient($http)->refresh('refresh-1');
    $body = (string) $http->requests[0]->getBody();
    parse_str($body, $params);

    expect($token->accessToken)->toBe('access-2')
        ->and($params['grant_type'])->toBe('refresh_token')
        ->and($params['refresh_token'])->toBe('refresh-1');
});
