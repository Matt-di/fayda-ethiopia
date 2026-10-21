<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel;

use Illuminate\Contracts\Session\Session;
use Mattdi\Fayda\Core\Contracts\StateStore;

final class SessionStateStore implements StateStore
{
    public function __construct(
        private readonly Session $session,
        private readonly string $key = 'fayda.oidc.states',
    ) {}

    public function put(string $state, array $payload, int $ttlSeconds): void
    {
        $states = $this->session->get($this->key, []);

        if (! is_array($states)) {
            $states = [];
        }

        $states[$state] = array_merge($payload, ['_expires_at' => time() + $ttlSeconds]);

        $this->session->put($this->key, $states);
    }

    public function pull(string $state): ?array
    {
        $states = $this->session->get($this->key, []);

        if (! is_array($states) || ! isset($states[$state]) || ! is_array($states[$state])) {
            return null;
        }

        $entry = $states[$state];
        unset($states[$state]);

        $this->session->put($this->key, $states);

        if (($entry['_expires_at'] ?? 0) < time()) {
            return null;
        }

        unset($entry['_expires_at']);

        return $entry;
    }
}
