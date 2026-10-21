<?php

declare(strict_types=1);

use Mattdi\Fayda\Core\Pkce;

it('generates a valid code verifier', function (): void {
    $verifier = Pkce::verifier();

    expect(strlen($verifier))->toBeBetween(43, 128)
        ->and($verifier)->toMatch('/^[A-Za-z0-9\-._~]+$/');
});

it('creates the RFC 7636 S256 challenge', function (): void {
    $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    expect(Pkce::challenge($verifier))->toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
});

it('rejects verifiers outside the RFC length bounds', function (): void {
    Pkce::verifier(42);
})->throws(InvalidArgumentException::class);

it('generates unique state and nonce values', function (): void {
    expect(Pkce::randomState())->not->toBe(Pkce::randomState())
        ->and(Pkce::randomNonce())->not->toBe(Pkce::randomNonce());
});
