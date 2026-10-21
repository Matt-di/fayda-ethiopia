<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Mattdi\Fayda\Core\Exceptions\InvalidTokenException;
use Mattdi\Fayda\Core\Exceptions\UserInfoException;
use Mattdi\Fayda\Core\UserInfoClient;
use Mattdi\Fayda\Tests\Support\FakeHttpClient;

function faydaUserInfoClient(FakeHttpClient $http): UserInfoClient
{
    return new UserInfoClient(
        'https://idp.test/userinfo',
        $http,
        faydaJwtVerifier($http),
        'https://idp.test',
        'client-1',
    );
}

it('verifies a signed JWT userinfo response', function (): void {
    $jwt = faydaSign(faydaUserInfoClaims());
    $http = new FakeHttpClient([faydaJwtResponse($jwt), faydaJwksResponse()]);

    $claims = faydaUserInfoClient($http)->fetch('access-1');

    expect($claims['sub'])->toBe('fayda-subject-1')
        ->and($claims['name'])->toBe('Abebe Bikila')
        ->and($http->requests[0]->getHeaderLine('Authorization'))->toBe('Bearer access-1');
});

it('accepts a JSON userinfo response', function (): void {
    $http = new FakeHttpClient([
        faydaJsonResponse(['sub' => 'fayda-subject-1', 'name' => 'Abebe Bikila']),
    ]);

    $claims = faydaUserInfoClient($http)->fetch('access-1');

    expect($claims['name'])->toBe('Abebe Bikila');
});

it('rejects a JSON userinfo response without a subject', function (): void {
    $http = new FakeHttpClient([
        faydaJsonResponse(['name' => 'Abebe Bikila']),
    ]);

    faydaUserInfoClient($http)->fetch('access-1');
})->throws(UserInfoException::class, 'subject');

it('rejects a userinfo JWT with a bad signature', function (): void {
    $jwt = faydaSign(faydaUserInfoClaims());
    $tampered = substr($jwt, 0, -4).'abcd';
    $http = new FakeHttpClient([faydaJwtResponse($tampered), faydaJwksResponse()]);

    faydaUserInfoClient($http)->fetch('access-1');
})->throws(InvalidTokenException::class);

it('throws a typed exception for a userinfo HTTP error', function (): void {
    $http = new FakeHttpClient([
        new Response(401, ['Content-Type' => 'application/json'], '{"error":"invalid_token"}'),
    ]);

    try {
        faydaUserInfoClient($http)->fetch('access-1');
    } catch (UserInfoException $e) {
        expect($e->statusCode())->toBe(401);

        return;
    }

    $this->fail('Expected a UserInfoException.');
});
