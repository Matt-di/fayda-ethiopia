<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Contracts;

interface StateStore
{
    /**
     * Persist the state payload for an authorization request.
     *
     * @param  array<string, mixed>  $payload
     */
    public function put(string $state, array $payload, int $ttlSeconds): void;

    /**
     * Retrieve and remove a state payload. Returns null when unknown or expired.
     *
     * @return array<string, mixed>|null
     */
    public function pull(string $state): ?array;
}
