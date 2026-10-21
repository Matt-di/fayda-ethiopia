<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Mattdi\Fayda\Core\Dto\FaydaUser;

final class NullUserResolver implements FaydaUserResolver
{
    public function resolve(FaydaUser $user): ?Authenticatable
    {
        return null;
    }
}
