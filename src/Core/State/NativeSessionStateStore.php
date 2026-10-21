<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\State;

use Mattdi\Fayda\Core\Contracts\StateStore;

/**
 * Session-backed state store for plain PHP applications.
 *
 * The store keeps one entry per authorization request, keyed by the OAuth
 * "state" value, and removes it as soon as it is pulled (single use).
 */
final class NativeSessionStateStore implements StateStore
{
    /** @var array<string, mixed> */
    private array $memory = [];

    public function __construct(
        private readonly string $key = 'fayda_oidc_states',
        private readonly bool $autoStart = true,
    ) {}

    public function put(string $state, array $payload, int $ttlSeconds): void
    {
        $payload['_expires_at'] = time() + $ttlSeconds;
        $payload['_created_at'] = time();

        $this->write($state, $payload);
    }

    public function pull(string $state): ?array
    {
        $entry = $this->read($state);

        if ($entry === null) {
            return null;
        }

        $this->forget($state);

        if (($entry['_expires_at'] ?? 0) < time()) {
            return null;
        }

        unset($entry['_expires_at'], $entry['_created_at']);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(string $state, array $payload): void
    {
        if ($this->sessionActive()) {
            $_SESSION[$this->key][$state] = $payload;

            return;
        }

        $this->memory[$state] = $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $state): ?array
    {
        if ($this->sessionActive()) {
            $entry = $_SESSION[$this->key][$state] ?? null;

            return is_array($entry) ? $entry : null;
        }

        $entry = $this->memory[$state] ?? null;

        return is_array($entry) ? $entry : null;
    }

    private function forget(string $state): void
    {
        if ($this->sessionActive()) {
            unset($_SESSION[$this->key][$state]);

            return;
        }

        unset($this->memory[$state]);
    }

    private function sessionActive(): bool
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }

        if (! $this->autoStart) {
            return false;
        }

        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                'Cannot start a PHP session because headers were already sent at '.$file.':'.$line.'. '
                .'Start the session before rendering output, or provide a custom StateStore implementation.'
            );
        }

        session_start();

        return true;
    }
}
