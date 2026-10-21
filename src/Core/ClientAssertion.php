<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core;

use Firebase\JWT\JWT;
use Mattdi\Fayda\Core\Crypto\RsaJwk;
use Mattdi\Fayda\Core\Exceptions\ConfigurationException;

final class ClientAssertion
{
    /**
     * @param  array<string, mixed>  $config  The full package configuration array.
     */
    public function __construct(private readonly array $config) {}

    /**
     * Create the signed private_key_jwt used to authenticate the client.
     */
    public function sign(string $audience): string
    {
        $client = $this->clientConfig();

        if (! isset($client['id']) || ! is_string($client['id']) || $client['id'] === '') {
            throw new ConfigurationException('The Fayda client id is not configured.');
        }

        $ttl = (int) ($client['assertion_ttl'] ?? 300);
        $ttl = max(30, min($ttl, 300));
        $now = time();

        $payload = [
            'iss' => $client['id'],
            'sub' => $client['id'],
            'aud' => $audience,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $kid = isset($client['key_id']) && is_string($client['key_id']) && $client['key_id'] !== ''
            ? $client['key_id']
            : null;

        return JWT::encode($payload, $this->privateKey(), 'RS256', $kid);
    }

    private function privateKey(): \OpenSSLAsymmetricKey
    {
        $client = $this->clientConfig();
        $pem = $this->resolvePem($client);

        $key = openssl_pkey_get_private($pem);

        if ($key === false) {
            throw new ConfigurationException(
                'The configured Fayda private key could not be loaded: '.openssl_error_string()
            );
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new ConfigurationException('The configured Fayda private key must be an RSA key.');
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $client
     */
    private function resolvePem(array $client): string
    {
        if (isset($client['private_key']) && is_string($client['private_key']) && trim($client['private_key']) !== '') {
            return $client['private_key'];
        }

        if (isset($client['private_key_path']) && is_string($client['private_key_path']) && $client['private_key_path'] !== '') {
            if (! is_file($client['private_key_path']) || ! is_readable($client['private_key_path'])) {
                throw new ConfigurationException(
                    'The Fayda private key file does not exist or is not readable: '.$client['private_key_path']
                );
            }

            $pem = file_get_contents($client['private_key_path']);

            if ($pem === false) {
                throw new ConfigurationException('The Fayda private key file could not be read.');
            }

            return $pem;
        }

        if (isset($client['private_jwk_base64']) && is_string($client['private_jwk_base64']) && $client['private_jwk_base64'] !== '') {
            $json = base64_decode($client['private_jwk_base64'], true);

            if ($json === false) {
                throw new ConfigurationException('The base64 encoded Fayda private JWK is invalid.');
            }

            return RsaJwk::toPem($this->decodeJwk($json));
        }

        if (isset($client['private_jwk'])) {
            $jwk = $client['private_jwk'];

            if (is_string($jwk)) {
                return RsaJwk::toPem($this->decodeJwk($jwk));
            }

            if (is_array($jwk)) {
                return RsaJwk::toPem($jwk);
            }
        }

        throw new ConfigurationException(
            'No Fayda private key is configured. Set client.private_key, client.private_key_path, '
            .'client.private_jwk_base64, or client.private_jwk.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJwk(string $json): array
    {
        try {
            $jwk = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigurationException('The Fayda private JWK is not valid JSON: '.$e->getMessage());
        }

        if (! is_array($jwk)) {
            throw new ConfigurationException('The Fayda private JWK must decode to a JSON object.');
        }

        return $jwk;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientConfig(): array
    {
        $client = $this->config['client'] ?? [];

        if (! is_array($client)) {
            throw new ConfigurationException('The "client" Fayda configuration must be an array.');
        }

        return $client;
    }
}
