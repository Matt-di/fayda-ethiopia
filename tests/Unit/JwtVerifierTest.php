<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Mattdi\Fayda\Core\Exceptions\InvalidTokenException;
use Mattdi\Fayda\Core\JwtVerifier;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;

beforeEach(function (): void {
    JWT::$timestamp = null;
    JWT::$leeway = 0;
});

function faydaVerifier(FakeHttpClient $http): JwtVerifier
{
    return faydaJwtVerifier($http);
}

it('verifies a valid ID token', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1'));

    $claims = faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);

    expect($claims['sub'])->toBe('fayda-subject-1')
        ->and($claims['aud'])->toBe('client-1');
});

it('rejects a nonce mismatch', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1'));

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-2']);
})->throws(InvalidTokenException::class, 'nonce');

it('rejects the wrong audience', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1', ['aud' => 'other-client']));

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class, 'aud');

it('rejects an expired token', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1', [
        'iat' => time() - 600,
        'exp' => time() - 300,
    ]));

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class, 'expired');

it('requires exp and iat claims', function (string $claim): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $claims = faydaIdTokenClaims('nonce-1');
    unset($claims[$claim]);
    $token = faydaSign($claims);

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);
})->with(['exp', 'iat'])
    ->throws(InvalidTokenException::class, 'missing or invalid');

it('requires an issuer claim', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $claims = faydaIdTokenClaims('nonce-1');
    unset($claims['iss']);
    $token = faydaSign($claims);

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class, 'iss');

it('verifies a valid at_hash claim', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1', [
        'at_hash' => faydaAccessTokenHash('access-1'),
    ]));

    $claims = faydaVerifier($http)->verify($token, [
        'nonce' => 'nonce-1',
        'access_token' => 'access-1',
    ]);

    expect($claims['at_hash'])->toBe(faydaAccessTokenHash('access-1'));
});

it('rejects an at_hash claim that does not match the access token', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1', [
        'at_hash' => faydaAccessTokenHash('access-1'),
    ]));

    faydaVerifier($http)->verify($token, [
        'nonce' => 'nonce-1',
        'access_token' => 'access-2',
    ]);
})->throws(InvalidTokenException::class, 'at_hash');

it('rejects an at_hash claim when the access token is unavailable', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1', [
        'at_hash' => faydaAccessTokenHash('access-1'),
    ]));

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class, 'at_hash');

it('rejects a tampered signature', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1'));
    $tampered = substr($token, 0, -4).'abcd';

    faydaVerifier($http)->verify($tampered, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class);

it('refreshes the JWKS when an unknown kid is used', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse(), faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1'), 'rotated-key');

    faydaVerifier($http)->verify($token, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class, 'rotated-key');

it('rejects an unsupported algorithm header', function (): void {
    $http = new FakeHttpClient([faydaJwksResponse()]);
    $token = faydaSign(faydaIdTokenClaims('nonce-1'));

    $parts = explode('.', $token);
    $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    $header['alg'] = 'none';
    $parts[0] = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $noneToken = implode('.', $parts);

    faydaVerifier($http)->verify($noneToken, ['nonce' => 'nonce-1']);
})->throws(InvalidTokenException::class);
