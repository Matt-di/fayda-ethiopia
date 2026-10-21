<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use GuzzleHttp\Psr7\HttpFactory;
use Mattdi\Fayda\Core\Exceptions\DiscoveryException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final class Discovery
{
    public function __construct(
        private readonly string $issuer,
        private readonly ClientInterface $http,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    /**
     * @return array{authorization: string, token: string, userinfo: string, jwks: string}
     */
    public function endpoints(): array
    {
        $document = $this->document();

        $endpoints = [
            'authorization' => $document['authorization_endpoint'] ?? null,
            'token' => $document['token_endpoint'] ?? null,
            'userinfo' => $document['userinfo_endpoint'] ?? null,
            'jwks' => $document['jwks_uri'] ?? null,
        ];

        foreach ($endpoints as $name => $value) {
            if (! is_string($value) || $value === '') {
                throw new DiscoveryException(
                    'The Fayda discovery document does not contain a valid "'.$name.'" endpoint.'
                );
            }
        }

        /** @var array{authorization: string, token: string, userinfo: string, jwks: string} $endpoints */
        return $endpoints;
    }

    /**
     * @return array<string, mixed>
     */
    private function document(): array
    {
        $cacheKey = 'fayda.discovery.'.sha1($this->issuer);

        if ($this->cache !== null) {
            $cached = $this->cache->get($cacheKey);

            if (is_array($cached) && isset($cached['issuer'])) {
                return $cached;
            }
        }

        $url = rtrim($this->issuer, '/').'/.well-known/openid-configuration';
        $factory = $this->requestFactory ?? new HttpFactory;

        try {
            $response = $this->http->sendRequest(
                $factory->createRequest('GET', $url)->withHeader('Accept', 'application/json')
            );
        } catch (ClientExceptionInterface $e) {
            throw new DiscoveryException('Could not fetch the Fayda discovery document: '.$e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new DiscoveryException(
                'The Fayda discovery endpoint returned HTTP '.$response->getStatusCode().'.'
            );
        }

        try {
            $document = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DiscoveryException('The Fayda discovery document is not valid JSON.', 0, $e);
        }

        if (! is_array($document)) {
            throw new DiscoveryException('The Fayda discovery document must be a JSON object.');
        }

        $documentIssuer = $document['issuer'] ?? null;

        if (! is_string($documentIssuer) || rtrim($documentIssuer, '/') !== rtrim($this->issuer, '/')) {
            throw new DiscoveryException(
                'The Fayda discovery document issuer does not match the configured issuer.'
            );
        }

        if ($this->cache !== null) {
            $this->cache->set($cacheKey, $document, $this->cacheTtl);
        }

        return $document;
    }
}
