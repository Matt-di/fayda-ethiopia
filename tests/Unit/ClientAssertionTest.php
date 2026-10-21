<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Mattdi\Fayda\Core\ClientAssertion;
use Mattdi\Fayda\Core\Exceptions\ConfigurationException;

it('signs a private_key_jwt from a PEM file', function (): void {
    $assertion = new ClientAssertion(faydaConfig());
    $jwt = $assertion->sign('https://idp.test/token');
    $claims = (array) JWT::decode($jwt, new Key(faydaPublicKeyPem(), 'RS256'));

    expect($claims['iss'])->toBe('client-1')
        ->and($claims['sub'])->toBe('client-1')
        ->and($claims['aud'])->toBe('https://idp.test/token')
        ->and($claims['jti'])->toBeString()
        ->and($claims['exp'] - $claims['iat'])->toBe(300);
});

it('signs from a base64 encoded private JWK', function (): void {
    $config = faydaConfig([
        'client' => [
            'private_key_path' => null,
            'private_jwk_base64' => base64_encode(faydaFixture('private-jwk.json')),
        ],
    ]);

    $jwt = (new ClientAssertion($config))->sign('https://idp.test/token');
    $claims = (array) JWT::decode($jwt, new Key(faydaPublicKeyPem(), 'RS256'));

    expect($claims['iss'])->toBe('client-1');
});

it('caps the assertion lifetime at five minutes', function (): void {
    $config = faydaConfig(['client' => ['assertion_ttl' => 7200]]);
    $jwt = (new ClientAssertion($config))->sign('https://idp.test/token');
    $claims = (array) JWT::decode($jwt, new Key(faydaPublicKeyPem(), 'RS256'));

    expect($claims['exp'] - $claims['iat'])->toBe(300);
});

it('fails when no private key is configured', function (): void {
    $config = faydaConfig([
        'client' => [
            'private_key_path' => null,
            'private_key' => null,
            'private_jwk' => null,
            'private_jwk_base64' => null,
        ],
    ]);

    (new ClientAssertion($config))->sign('https://idp.test/token');
})->throws(ConfigurationException::class);
