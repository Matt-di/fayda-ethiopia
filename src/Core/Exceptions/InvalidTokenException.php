<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Exceptions;

final class InvalidTokenException extends FaydaException
{
    public static function malformed(string $reason): self
    {
        return new self('The Fayda token is malformed: '.$reason);
    }

    public static function signature(string $reason): self
    {
        return new self('The Fayda token signature is invalid: '.$reason);
    }

    public static function expired(): self
    {
        return new self('The Fayda token has expired.');
    }

    public static function claim(string $reason): self
    {
        return new self('The Fayda token contains an invalid claim: '.$reason);
    }

    public static function unknownKey(string $kid): self
    {
        return new self('No Fayda signing key was found for key id "'.$kid.'".');
    }

    public static function missingKeyId(): self
    {
        return new self('The Fayda token has no "kid" header and the JWKS contains multiple keys.');
    }

    public static function missingIdToken(): self
    {
        return new self('The Fayda token response did not contain an id_token.');
    }
}
