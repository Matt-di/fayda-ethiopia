<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Mattdi\Fayda\FaydaClient;

/**
 * @method static string authorizationUrl(array<string, mixed> $overrides = [], array<string, mixed> $context = [])
 * @method static \Mattdi\Fayda\Core\Dto\AuthorizationRequest authorizationRequest(array<string, mixed> $overrides = [], array<string, mixed> $context = [])
 * @method static \Mattdi\Fayda\Core\Dto\FaydaUser handleCallback(string $code, ?string $state = null, ?string $error = null, ?string $errorDescription = null, ?string $iss = null)
 * @method static \Mattdi\Fayda\Core\Dto\LoginResult authenticate(string $code, ?string $state = null, ?string $error = null, ?string $errorDescription = null, ?string $iss = null)
 * @method static \Mattdi\Fayda\Core\Dto\TokenResponse refresh(string $refreshToken)
 * @method static array{authorization: string, token: string, userinfo: string, jwks: string} endpoints()
 * @method static array<string, mixed> config()
 *
 * @see FaydaClient
 */
final class Fayda extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'fayda';
    }
}
