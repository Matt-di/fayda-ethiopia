<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Dto;

final readonly class LoginResult
{
    /**
     * @param  array<string, mixed>  $idTokenClaims
     * @param  array<string, mixed>  $userInfoClaims
     */
    public function __construct(
        public FaydaUser $user,
        public TokenResponse $token,
        public array $idTokenClaims = [],
        public array $userInfoClaims = [],
    ) {}
}
