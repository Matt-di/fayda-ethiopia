<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Dto;

final readonly class FaydaUser
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public ?string $sub,
        public ?string $name = null,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $middleName = null,
        public ?string $nickname = null,
        public ?string $preferredUsername = null,
        public ?string $email = null,
        public ?bool $emailVerified = null,
        public ?string $phoneNumber = null,
        public ?bool $phoneNumberVerified = null,
        public ?string $gender = null,
        public ?string $birthdate = null,
        public ?string $picture = null,
        public ?string $nationality = null,
        public ?Address $address = null,
        public ?string $locale = null,
        public ?string $zoneinfo = null,
        public ?int $updatedAt = null,
        public array $claims = [],
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        return new self(
            sub: self::string($claims, 'sub'),
            name: self::string($claims, 'name'),
            givenName: self::string($claims, 'given_name'),
            familyName: self::string($claims, 'family_name'),
            middleName: self::string($claims, 'middle_name'),
            nickname: self::string($claims, 'nickname'),
            preferredUsername: self::string($claims, 'preferred_username'),
            email: self::string($claims, 'email'),
            emailVerified: self::bool($claims, 'email_verified'),
            phoneNumber: self::string($claims, 'phone_number') ?? self::string($claims, 'phone'),
            phoneNumberVerified: self::bool($claims, 'phone_number_verified') ?? self::bool($claims, 'phone_verified'),
            gender: self::string($claims, 'gender'),
            birthdate: self::string($claims, 'birthdate'),
            picture: self::string($claims, 'picture'),
            nationality: self::string($claims, 'nationality'),
            address: Address::fromArray($claims['address'] ?? null),
            locale: self::string($claims, 'locale'),
            zoneinfo: self::string($claims, 'zoneinfo'),
            updatedAt: isset($claims['updated_at']) && is_numeric($claims['updated_at']) ? (int) $claims['updated_at'] : null,
            claims: $claims,
        );
    }

    public function claim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'sub' => $this->sub,
            'name' => $this->name,
            'given_name' => $this->givenName,
            'family_name' => $this->familyName,
            'middle_name' => $this->middleName,
            'nickname' => $this->nickname,
            'preferred_username' => $this->preferredUsername,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'phone_number' => $this->phoneNumber,
            'phone_number_verified' => $this->phoneNumberVerified,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'picture' => $this->picture,
            'nationality' => $this->nationality,
            'address' => $this->address?->toArray(),
            'locale' => $this->locale,
            'zoneinfo' => $this->zoneinfo,
            'updated_at' => $this->updatedAt,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function string(array $claims, string $key): ?string
    {
        if (! isset($claims[$key]) || ! is_scalar($claims[$key])) {
            return null;
        }

        $value = trim((string) $claims[$key]);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function bool(array $claims, string $key): ?bool
    {
        if (! array_key_exists($key, $claims)) {
            return null;
        }

        return filter_var($claims[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
