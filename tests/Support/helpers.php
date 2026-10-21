<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\Response;
use Mattdi\Fayda\Core\Dto\FaydaUser;
use Mattdi\Fayda\Core\JwksProvider;
use Mattdi\Fayda\Core\JwtVerifier;
use Mattdi\Fayda\Core\Pkce;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function faydaConfig(array $overrides = []): array
{
    $config = [
        'issuer' => 'https://idp.test',
        'discovery' => ['enabled' => false, 'cache_ttl' => 3600],
        'endpoints' => [
            'authorization' => 'https://idp.test/authorize',
            'token' => 'https://idp.test/token',
            'userinfo' => 'https://idp.test/userinfo',
            'jwks' => 'https://idp.test/jwks',
        ],
        'client' => [
            'id' => 'client-1',
            'assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'assertion_ttl' => 300,
            'private_key_path' => dirname(__DIR__).'/Fixtures/private-key.pem',
            'key_id' => 'test-key',
        ],
        'redirect_uri' => 'https://app.test/auth/fayda/callback',
        'scopes' => ['openid', 'profile', 'email'],
        'acr_values' => ['mosip:idp:acr:generated-code'],
        'claims' => [],
        'ui_locales' => 'en',
        'display' => 'page',
        'pkce' => true,
        'state_ttl' => 600,
        'require_id_token' => true,
        'fetch_userinfo' => true,
        'clock_skew' => 0,
        'jwks_cache_ttl' => 3600,
    ];

    return array_replace_recursive($config, $overrides);
}

function faydaFixture(string $name): string
{
    $contents = file_get_contents(dirname(__DIR__).'/Fixtures/'.$name);

    if ($contents === false) {
        throw new RuntimeException('Missing fixture: '.$name);
    }

    return $contents;
}

function faydaPrivateKey(): OpenSSLAsymmetricKey
{
    $key = openssl_pkey_get_private(faydaFixture('private-key.pem'));

    if ($key === false) {
        throw new RuntimeException('Could not load the test private key.');
    }

    return $key;
}

function faydaPublicKeyPem(): string
{
    $details = openssl_pkey_get_details(faydaPrivateKey());

    if ($details === false) {
        throw new RuntimeException('Could not read the test public key.');
    }

    return $details['key'];
}

/**
 * @param  array<string, mixed>  $claims
 */
function faydaSign(array $claims, string $kid = 'test-key'): string
{
    return JWT::encode($claims, faydaPrivateKey(), 'RS256', $kid);
}

function faydaAccessTokenHash(string $accessToken): string
{
    return Pkce::base64Url(substr(hash('sha256', $accessToken, true), 0, 16));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function faydaIdTokenClaims(string $nonce, array $overrides = []): array
{
    $now = time();

    return array_replace([
        'iss' => 'https://idp.test',
        'aud' => 'client-1',
        'sub' => 'fayda-subject-1',
        'iat' => $now,
        'exp' => $now + 300,
        'nonce' => $nonce,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function faydaUserInfoClaims(array $overrides = []): array
{
    $now = time();

    return array_replace([
        'iss' => 'https://idp.test',
        'aud' => 'client-1',
        'sub' => 'fayda-subject-1',
        'iat' => $now,
        'exp' => $now + 300,
        'name' => 'Abebe Bikila',
        'given_name' => 'Abebe',
        'family_name' => 'Bikila',
        'email' => 'abebe@example.test',
        'email_verified' => true,
        'phone_number' => '+251911000000',
        'gender' => 'male',
        'birthdate' => '1990-01-01',
        'nationality' => 'ETH',
        'address' => [
            'region' => 'Addis Ababa',
            'zone' => 'Bole',
            'woreda' => '03',
        ],
    ], $overrides);
}

function faydaJwksResponse(): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], faydaFixture('jwks.json'));
}

/**
 * @param  array<string, mixed>  $data
 */
function faydaJsonResponse(array $data, int $status = 200): Response
{
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
}

function faydaJwtResponse(string $jwt): Response
{
    return new Response(200, ['Content-Type' => 'application/jwt'], $jwt);
}

/**
 * @return array<string, string>
 */
function faydaQuery(string $url): array
{
    $query = parse_url($url, PHP_URL_QUERY);
    $params = [];

    if (is_string($query)) {
        parse_str($query, $params);
    }

    /** @var array<string, string> $params */
    return $params;
}

function faydaUser(string $sub = 'fayda-subject-1'): FaydaUser
{
    return FaydaUser::fromClaims([
        'sub' => $sub,
        'name' => 'Abebe Bikila',
        'email' => 'abebe@example.test',
        'email_verified' => true,
    ]);
}

function faydaJwtVerifier(FakeHttpClient $http): JwtVerifier
{
    return new JwtVerifier(
        new JwksProvider('https://idp.test/jwks', $http),
        'https://idp.test',
        'client-1',
        0,
    );
}
