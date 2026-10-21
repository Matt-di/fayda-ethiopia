<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Mattdi\Fayda\Core\Dto\FaydaUser;
use Mattdi\Fayda\Core\Dto\LoginResult;

final class FaydaLoginSucceeded
{
    public function __construct(
        public readonly LoginResult $result,
        public readonly ?Authenticatable $authenticatable = null,
    ) {}

    public function user(): FaydaUser
    {
        return $this->result->user;
    }
}
