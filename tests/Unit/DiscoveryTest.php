<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Mattdi\Fayda\Core\Discovery;
use Mattdi\Fayda\Core\Exceptions\DiscoveryException;
use Mattdi\Fayda\Tests\Support\ArrayCache;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;

function faydaDiscoveryDocument(array $overrides = []): Response
{
    return faydaJsonResponse(array_replace([
        'issuer' => 'https://idp.test',
        'authorization_endpoint' => 'https://idp.test/authorize',
        'token_endpoint' => 'https://idp.test/token',
        'userinfo_endpoint' => 'https://idp.test/userinfo',
        'jwks_uri' => 'https://idp.test/jwks',
    ], $overrides));
}

it('discovers the Fayda endpoints', function (): void {
    $http = new FakeHttpClient([faydaDiscoveryDocument()]);
    $discovery = new Discovery('https://idp.test', $http);

    $endpoints = $discovery->endpoints();

    expect($endpoints)->toBe([
        'authorization' => 'https://idp.test/authorize',
        'token' => 'https://idp.test/token',
        'userinfo' => 'https://idp.test/userinfo',
        'jwks' => 'https://idp.test/jwks',
    ])->and($http->requests)->toHaveCount(1);
});

it('caches the discovery document', function (): void {
    $http = new FakeHttpClient([faydaDiscoveryDocument()]);
    $cache = new ArrayCache;
    $discovery = new Discovery('https://idp.test', $http, $cache, 3600);

    $discovery->endpoints();
    $discovery->endpoints();

    expect($http->requests)->toHaveCount(1);
});

it('rejects a discovery document from another issuer', function (): void {
    $http = new FakeHttpClient([
        faydaDiscoveryDocument(['issuer' => 'https://attacker.test']),
    ]);

    (new Discovery('https://idp.test', $http))->endpoints();
})->throws(DiscoveryException::class, 'issuer');

it('rejects a discovery document with missing endpoints', function (): void {
    $http = new FakeHttpClient([
        faydaDiscoveryDocument(['token_endpoint' => null]),
    ]);

    (new Discovery('https://idp.test', $http))->endpoints();
})->throws(DiscoveryException::class, 'token');

it('rejects a malformed discovery document', function (): void {
    $http = new FakeHttpClient([
        new Response(200, ['Content-Type' => 'application/json'], 'not-json'),
    ]);

    (new Discovery('https://idp.test', $http))->endpoints();
})->throws(DiscoveryException::class);
