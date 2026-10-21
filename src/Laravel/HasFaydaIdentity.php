<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Mattdi\Fayda\Laravel\Models\FaydaIdentity;

trait HasFaydaIdentity
{
    public function faydaIdentities(): HasMany
    {
        return $this->hasMany(FaydaIdentity::class, 'user_id');
    }
}
