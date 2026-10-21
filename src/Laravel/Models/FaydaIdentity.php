<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaydaIdentity extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'claims' => 'array',
        'last_login_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return (string) config('fayda.persistence.table', 'fayda_identities');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            (string) config('fayda.persistence.user_model', 'App\\Models\\User'),
            'user_id'
        );
    }
}
