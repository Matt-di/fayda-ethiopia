<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use GuzzleHttp\Psr7\HttpFactory;
use Mattdi\Fayda\Core\Exceptions\UserInfoException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class UserInfoClient
{
    public function __construct(
        private readonly string $endpoint,
        private readonly ClientInterface $http,
        private readonly JwtVerifier $verifier,
        private readonly ?string $issuer = null,
        private readonly ?string $audience = null,
        private readonly ?RequestFactoryInterface $requestFactory = null,
    ) {}

    /**
     * Fetch and verify the userinfo claims.
     *
     * @return array<string, mixed>
     */
    public function fetch(string $accessToken): array
    {
        if ($accessToken === '') {
            throw new UserInfoException('The access token is empty.');
        }

        $factory = $this->requestFactory ?? new HttpFactory;

        $request = $factory->createRequest('GET', $this->endpoint)
            ->withHeader('Authorization', 'Bearer '.$accessToken)
            ->withHeader('Accept', 'application/json, application/jwt');

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new UserInfoException('The Fayda userinfo request failed: '.$e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $body = trim((string) $response->getBody());

        if ($statusCode < 200 || $statusCode >= 300) {
            throw UserInfoException::fromResponse($statusCode, $body);
        }

        if ($body === '') {
            throw UserInfoException::invalidResponse('The response body is empty.');
        }

        $contentType = strtolower($response->getHeaderLine('Content-Type'));

        if (str_contains($contentType, 'jwt') || $this->looksLikeJwt($body)) {
            return $this->verifier->verify($body, [
                'issuer' => $this->issuer,
                'audience' => $this->audience,
                'require_sub' => true,
            ]);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw UserInfoException::invalidResponse('The response is not valid JSON.');
        }

        // Some providers return the signed JWT as a JSON string.
        if (is_string($data)) {
            return $this->verifier->verify($data, [
                'issuer' => $this->issuer,
                'audience' => $this->audience,
                'require_sub' => true,
            ]);
        }

        if (! is_array($data)) {
            throw UserInfoException::invalidResponse('The response must be a JSON object.');
        }

        return $data;
    }

    private function looksLikeJwt(string $body): bool
    {
        if (str_starts_with($body, '{')) {
            return false;
        }

        return substr_count($body, '.') === 2;
    }
}
