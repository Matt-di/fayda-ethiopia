<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Core\Dto;

final readonly class AuthorizationRequest
{
    public function __construct(
        public string $url,
        public string $state,
        public string $nonce,
    ) {}
}
