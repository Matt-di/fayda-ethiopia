<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

final class Pkce
{
    /**
     * Generate an RFC 7636 code verifier.
     */
    public static function verifier(int $length = 64): string
    {
        if ($length < 43 || $length > 128) {
            throw new \InvalidArgumentException('A PKCE code verifier must be between 43 and 128 characters.');
        }

        return substr(self::base64Url(random_bytes(96)), 0, $length);
    }

    /**
     * Generate the S256 code challenge for a verifier.
     */
    public static function challenge(string $verifier): string
    {
        if (strlen($verifier) < 43 || strlen($verifier) > 128) {
            throw new \InvalidArgumentException('A PKCE code verifier must be between 43 and 128 characters.');
        }

        return self::base64Url(hash('sha256', $verifier, true));
    }

    public static function randomState(int $bytes = 24): string
    {
        return self::base64Url(random_bytes($bytes));
    }

    public static function randomNonce(int $bytes = 32): string
    {
        return self::base64Url(random_bytes($bytes));
    }

    public static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64url value.');
        }

        return $decoded;
    }
}
