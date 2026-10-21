<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Dto;

final readonly class Address
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $formatted = null,
        public ?string $streetAddress = null,
        public ?string $locality = null,
        public ?string $region = null,
        public ?string $zone = null,
        public ?string $woreda = null,
        public ?string $kebele = null,
        public ?string $postalCode = null,
        public ?string $country = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>|string|object|null  $data
     */
    public static function fromArray(array|string|object|null $data): ?self
    {
        if ($data === null || $data === []) {
            return null;
        }

        if (is_string($data)) {
            return new self(formatted: $data, raw: ['formatted' => $data]);
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        return new self(
            formatted: self::string($data, 'formatted'),
            streetAddress: self::string($data, 'street_address') ?? self::string($data, 'street'),
            locality: self::string($data, 'locality') ?? self::string($data, 'city'),
            region: self::string($data, 'region'),
            zone: self::string($data, 'zone'),
            woreda: self::string($data, 'woreda'),
            kebele: self::string($data, 'kebele'),
            postalCode: self::string($data, 'postal_code'),
            country: self::string($data, 'country'),
            raw: $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'formatted' => $this->formatted,
            'street_address' => $this->streetAddress,
            'locality' => $this->locality,
            'region' => $this->region,
            'zone' => $this->zone,
            'woreda' => $this->woreda,
            'kebele' => $this->kebele,
            'postal_code' => $this->postalCode,
            'country' => $this->country,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): ?string
    {
        if (! isset($data[$key]) || ! is_scalar($data[$key])) {
            return null;
        }

        $value = trim((string) $data[$key]);

        return $value === '' ? null : $value;
    }
}
