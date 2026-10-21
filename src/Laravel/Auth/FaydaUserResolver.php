<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Mattdi\Fayda\Core\Dto\FaydaUser;

interface FaydaUserResolver
{
    /**
     * Map a verified Fayda identity to an application user.
     *
     * Return null when the package should not log anybody in (for example
     * when the application wants to handle registration itself).
     */
    public function resolve(FaydaUser $user): ?Authenticatable;
}
