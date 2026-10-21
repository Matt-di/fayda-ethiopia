<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use GuzzleHttp\Psr7\HttpFactory;
use Mattdi\Fayda\Core\Exceptions\InvalidTokenException;
use Mattdi\Fayda\Core\Exceptions\JwksException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final class JwksProvider
{
    /** @var array<string, Key>|null */
    private ?array $memory = null;

    public function __construct(
        private readonly string $jwksUri,
        private readonly ClientInterface $http,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    /**
     * @return array<string, Key>
     */
    public function keys(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && $this->memory !== null) {
            return $this->memory;
        }

        $jwks = $this->load($forceRefresh);

        try {
            $keys = JWK::parseKeySet($jwks, 'RS256');
        } catch (\Throwable $e) {
            throw new JwksException('The Fayda JWKS response could not be parsed: '.$e->getMessage(), 0, $e);
        }

        if ($keys === []) {
            throw new JwksException('The Fayda JWKS response did not contain any usable keys.');
        }

        $this->memory = $keys;

        return $keys;
    }

    public function keyForKid(string $kid): Key
    {
        $keys = $this->keys();

        if (isset($keys[$kid])) {
            return $keys[$kid];
        }

        // The identity provider may have rotated keys since the last fetch.
        $keys = $this->keys(true);

        if (isset($keys[$kid])) {
            return $keys[$kid];
        }

        throw InvalidTokenException::unknownKey($kid);
    }

    public function singleKey(): Key
    {
        $keys = $this->keys();

        if (count($keys) === 1) {
            return reset($keys);
        }

        throw InvalidTokenException::missingKeyId();
    }

    /**
     * @return array<string, mixed>
     */
    private function load(bool $forceRefresh): array
    {
        $cacheKey = 'fayda.jwks.'.sha1($this->jwksUri);

        if ($this->cache !== null) {
            if ($forceRefresh) {
                $this->cache->delete($cacheKey);
            } else {
                $cached = $this->cache->get($cacheKey);

                if (is_array($cached) && isset($cached['keys']) && is_array($cached['keys'])) {
                    return $cached;
                }
            }
        }

        $factory = $this->requestFactory ?? new HttpFactory;

        try {
            $response = $this->http->sendRequest(
                $factory->createRequest('GET', $this->jwksUri)->withHeader('Accept', 'application/json')
            );
        } catch (ClientExceptionInterface $e) {
            throw new JwksException('Could not fetch the Fayda JWKS: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new JwksException('The Fayda JWKS endpoint returned HTTP '.$response->getStatusCode().'.');
        }

        $body = (string) $response->getBody();

        try {
            $jwks = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JwksException('The Fayda JWKS response is not valid JSON.', 0, $e);
        }

        if (! is_array($jwks) || ! isset($jwks['keys']) || ! is_array($jwks['keys']) || $jwks['keys'] === []) {
            throw new JwksException('The Fayda JWKS response does not contain a non-empty "keys" array.');
        }

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $jwks, $this->cacheTtl);
        }

        return $jwks;
    }
}
