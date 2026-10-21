<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Tests\Support;

use Mattdi\Fayda\Core\Contracts\StateStore;

final class InMemoryStateStore implements StateStore
{
    /** @var array<string, array<string, mixed>> */
    public array $states = [];

    public function put(string $state, array $payload, int $ttlSeconds): void
    {
        $payload['_expires_at'] = time() + $ttlSeconds;

        $this->states[$state] = $payload;
    }

    public function pull(string $state): ?array
    {
        $entry = $this->states[$state] ?? null;
        unset($this->states[$state]);

        if ($entry === null || ($entry['_expires_at'] ?? 0) < time()) {
            return null;
        }

        unset($entry['_expires_at']);

        return $entry;
    }
}
