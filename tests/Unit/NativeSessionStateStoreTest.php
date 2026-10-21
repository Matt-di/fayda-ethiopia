<?php

declare(strict_types=1);

use Mattdi\Fayda\Core\State\NativeSessionStateStore;

it('stores and consumes state exactly once', function (): void {
    $store = new NativeSessionStateStore(autoStart: false);

    $store->put('state-1', ['nonce' => 'nonce-1', 'code_verifier' => 'verifier-1'], 600);

    $entry = $store->pull('state-1');

    expect($entry['nonce'])->toBe('nonce-1')
        ->and($entry['code_verifier'])->toBe('verifier-1')
        ->and($entry)->not->toHaveKey('_expires_at')
        ->and($store->pull('state-1'))->toBeNull();
});

it('rejects an expired state entry', function (): void {
    $store = new NativeSessionStateStore(autoStart: false);

    $store->put('state-1', ['nonce' => 'nonce-1'], -1);

    expect($store->pull('state-1'))->toBeNull();
});

it('returns null for an unknown state', function (): void {
    $store = new NativeSessionStateStore(autoStart: false);

    expect($store->pull('missing'))->toBeNull();
});
