<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Mattdi\Fayda\Core\Exceptions\InvalidTokenException;

final class JwtVerifier
{
    public function __construct(
        private readonly JwksProvider $jwks,
        private readonly ?string $issuer = null,
        private readonly ?string $audience = null,
        private readonly int $clockSkew = 60,
    ) {}

    /**
     * Verify a JWT and return its claims.
     *
     * Supported options: issuer, audience, nonce, subject, require_sub.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function verify(string $jwt, array $options = []): array
    {
        $kid = $this->keyId($jwt);

        $key = $kid !== null
            ? $this->jwks->keyForKid($kid)
            : $this->jwks->singleKey();

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->clockSkew;

        try {
            $decoded = JWT::decode($jwt, $key);
        } catch (ExpiredException $e) {
            throw InvalidTokenException::expired();
        } catch (BeforeValidException $e) {
            throw InvalidTokenException::claim('The token is not valid yet.');
        } catch (SignatureInvalidException $e) {
            throw InvalidTokenException::signature($e->getMessage());
        } catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $e) {
            throw InvalidTokenException::malformed($e->getMessage());
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $claims = (array) $decoded;

        $this->assertClaims($claims, $options);

        return $claims;
    }

    private function keyId(string $jwt): ?string
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw InvalidTokenException::malformed('The token must have three segments.');
        }

        try {
            $header = json_decode(Pkce::base64UrlDecode($parts[0]), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException|\InvalidArgumentException $e) {
            throw InvalidTokenException::malformed('The token header is not valid JSON.');
        }

        if (! is_array($header)) {
            throw InvalidTokenException::malformed('The token header must be a JSON object.');
        }

        if (isset($header['alg']) && $header['alg'] !== 'RS256') {
            throw InvalidTokenException::malformed('Only RS256 tokens are accepted.');
        }

        return isset($header['kid']) && is_string($header['kid']) && $header['kid'] !== ''
            ? $header['kid']
            : null;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $options
     */
    private function assertClaims(array $claims, array $options): void
    {
        $issuer = $this->stringOption($options, 'issuer') ?? $this->issuer;

        if ($issuer !== null) {
            if (! isset($claims['iss']) || ! is_string($claims['iss']) || ! hash_equals($issuer, $claims['iss'])) {
                throw InvalidTokenException::claim('The "iss" claim does not match the configured issuer.');
            }
        }

        $audience = $this->stringOption($options, 'audience') ?? $this->audience;

        if ($audience !== null) {
            $this->assertAudience($claims, $audience);
        }

        $nonce = $this->stringOption($options, 'nonce');

        if ($nonce !== null) {
            if (! isset($claims['nonce']) || ! is_string($claims['nonce']) || ! hash_equals($nonce, $claims['nonce'])) {
                throw InvalidTokenException::claim('The "nonce" claim does not match the authorization request.');
            }
        }

        $subject = $this->stringOption($options, 'subject');

        if ($subject !== null) {
            if (! isset($claims['sub']) || ! is_string($claims['sub']) || ! hash_equals($subject, $claims['sub'])) {
                throw InvalidTokenException::claim('The "sub" claim does not match the expected subject.');
            }
        }

        if (($options['require_sub'] ?? false) === true && (! isset($claims['sub']) || $claims['sub'] === '')) {
            throw InvalidTokenException::claim('The token does not contain a "sub" claim.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertAudience(array $claims, string $audience): void
    {
        $aud = $claims['aud'] ?? null;

        if (is_string($aud)) {
            $audiences = [$aud];
        } elseif (is_array($aud)) {
            $audiences = array_values(array_filter($aud, 'is_string'));
        } else {
            throw InvalidTokenException::claim('The token does not contain an "aud" claim.');
        }

        if (! in_array($audience, $audiences, true)) {
            throw InvalidTokenException::claim('The "aud" claim does not match the client id.');
        }

        if (isset($claims['azp'])) {
            if (! is_string($claims['azp']) || ! hash_equals($audience, $claims['azp'])) {
                throw InvalidTokenException::claim('The "azp" claim does not match the client id.');
            }

            return;
        }

        if (count($audiences) > 1) {
            throw InvalidTokenException::claim('The token has multiple audiences but no "azp" claim.');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
