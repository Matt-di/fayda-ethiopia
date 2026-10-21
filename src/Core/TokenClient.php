<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use GuzzleHttp\Psr7\HttpFactory;
use Mattdi\Fayda\Core\Dto\TokenResponse;
use Mattdi\Fayda\Core\Exceptions\TokenRequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class TokenClient
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $clientId,
        private readonly string $assertionType,
        private readonly ClientAssertion $assertion,
        private readonly ClientInterface $http,
        private readonly ?RequestFactoryInterface $requestFactory = null,
        private readonly ?StreamFactoryInterface $streamFactory = null,
    ) {}

    public function exchangeCode(string $code, string $redirectUri, ?string $codeVerifier = null): TokenResponse
    {
        if ($code === '') {
            throw new TokenRequestException('The authorization code is empty.');
        }

        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId,
            'client_assertion_type' => $this->assertionType,
            'client_assertion' => $this->assertion->sign($this->endpoint),
        ];

        if ($codeVerifier !== null && $codeVerifier !== '') {
            $params['code_verifier'] = $codeVerifier;
        }

        return $this->post($params);
    }

    public function refresh(string $refreshToken): TokenResponse
    {
        if ($refreshToken === '') {
            throw new TokenRequestException('The refresh token is empty.');
        }

        return $this->post([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_assertion_type' => $this->assertionType,
            'client_assertion' => $this->assertion->sign($this->endpoint),
        ]);
    }

    /**
     * @param  array<string, string>  $params
     */
    private function post(array $params): TokenResponse
    {
        $factory = $this->requestFactory ?? new HttpFactory;
        $streams = $this->streamFactory ?? new HttpFactory;

        $request = $factory->createRequest('POST', $this->endpoint)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'application/json')
            ->withBody($streams->createStream(http_build_query($params, '', '&', PHP_QUERY_RFC3986)));

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TokenRequestException('The Fayda token request failed: '.$e->getMessage(), previous: $e);
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw TokenRequestException::invalidResponse($statusCode, $body, $e);
        }

        if (! is_array($data)) {
            throw TokenRequestException::invalidResponse($statusCode, $body);
        }

        if ($statusCode >= 400 || isset($data['error'])) {
            throw TokenRequestException::fromResponse($data, $statusCode, $body);
        }

        return TokenResponse::fromArray($data);
    }
}
