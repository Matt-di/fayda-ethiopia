<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Dto;

use Mattdi\Fayda\Core\Exceptions\TokenRequestException;

final readonly class TokenResponse
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $accessToken,
        public string $tokenType = 'Bearer',
        public ?int $expiresIn = null,
        public ?string $refreshToken = null,
        public ?string $idToken = null,
        public ?string $scope = null,
        public array $raw = [],
        public ?int $issuedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['access_token']) || ! is_string($data['access_token']) || $data['access_token'] === '') {
            throw TokenRequestException::invalidResponse(200, 'Missing access_token in token response.');
        }

        return new self(
            accessToken: $data['access_token'],
            tokenType: isset($data['token_type']) ? (string) $data['token_type'] : 'Bearer',
            expiresIn: isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            refreshToken: isset($data['refresh_token']) ? (string) $data['refresh_token'] : null,
            idToken: isset($data['id_token']) ? (string) $data['id_token'] : null,
            scope: isset($data['scope']) ? (string) $data['scope'] : null,
            raw: $data,
            issuedAt: time(),
        );
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        if ($this->expiresIn === null) {
            return null;
        }

        return (new \DateTimeImmutable)->setTimestamp(($this->issuedAt ?? time()) + $this->expiresIn);
    }

    public function isExpired(int $leewaySeconds = 0): bool
    {
        if ($this->expiresIn === null) {
            return false;
        }

        return time() >= (($this->issuedAt ?? time()) + $this->expiresIn + $leewaySeconds);
    }
}
