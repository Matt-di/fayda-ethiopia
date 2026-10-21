<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Crypto;

use Mattdi\Fayda\Core\Exceptions\ConfigurationException;

/**
 * Converts an RSA private JWK (RFC 7517 / RFC 7518) to PEM.
 *
 * The Fayda onboarding flow commonly provides a private key as a base64
 * encoded JWK, so the package must be able to sign without requiring the
 * integrator to convert it manually.
 */
final class RsaJwk
{
    private const REQUIRED_PARTS = ['n', 'e', 'd', 'p', 'q', 'dp', 'dq', 'qi'];

    /**
     * @param  array<string, mixed>  $jwk
     */
    public static function toPem(array $jwk): string
    {
        if (isset($jwk['kty']) && $jwk['kty'] !== 'RSA') {
            throw new ConfigurationException('Only RSA JWKs are supported for the Fayda client assertion.');
        }

        foreach (self::REQUIRED_PARTS as $part) {
            if (! isset($jwk[$part]) || ! is_string($jwk[$part]) || $jwk[$part] === '') {
                throw new ConfigurationException('The private JWK is missing the "'.$part.'" component.');
            }
        }

        $der = self::sequence(
            self::integer("\x00"),
            self::integer(self::decode($jwk['n'])),
            self::integer(self::decode($jwk['e'])),
            self::integer(self::decode($jwk['d'])),
            self::integer(self::decode($jwk['p'])),
            self::integer(self::decode($jwk['q'])),
            self::integer(self::decode($jwk['dp'])),
            self::integer(self::decode($jwk['dq'])),
            self::integer(self::decode($jwk['qi'])),
        );

        return "-----BEGIN RSA PRIVATE KEY-----\n"
            .chunk_split(base64_encode($der), 64, "\n")
            ."-----END RSA PRIVATE KEY-----\n";
    }

    private static function decode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            throw new ConfigurationException('The private JWK contains invalid base64url data.');
        }

        return $decoded;
    }

    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return self::tlv(0x02, $bytes);
    }

    private static function sequence(string ...$parts): string
    {
        return self::tlv(0x30, implode('', $parts));
    }

    private static function tlv(int $tag, string $value): string
    {
        return chr($tag).self::length(strlen($value)).$value;
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }
}
