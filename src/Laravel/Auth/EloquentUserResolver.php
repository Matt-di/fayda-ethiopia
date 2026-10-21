<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Mattdi\Fayda\Core\Dto\FaydaUser;
use Mattdi\Fayda\Laravel\Models\FaydaIdentity;

/**
 * Links a Fayda subject to an Eloquent user.
 *
 * Existing identities are always resolved. Creating a new user is opt-in via
 * fayda.persistence.create_users, because every application has different
 * registration and approval rules.
 */
final class EloquentUserResolver implements FaydaUserResolver
{
    public function resolve(FaydaUser $user): ?Authenticatable
    {
        if ($user->sub === null || $user->sub === '') {
            return null;
        }

        $clientId = (string) config('fayda.client.id');

        $identity = FaydaIdentity::query()
            ->where('subject', $user->sub)
            ->where('client_id', $clientId)
            ->first();

        if ($identity !== null) {
            $identity->forceFill([
                'claims' => $user->claims,
                'last_login_at' => now(),
            ])->save();

            $identityUser = $identity->user;

            return $identityUser instanceof Authenticatable ? $identityUser : null;
        }

        if (! (bool) config('fayda.persistence.create_users', false)) {
            return null;
        }

        $modelClass = (string) config('fayda.persistence.user_model', 'App\\Models\\User');

        if (! class_exists($modelClass) || ! is_a($modelClass, Model::class, true)) {
            return null;
        }

        $model = $this->findVerifiedEmailUser($modelClass, $user) ?? $this->createUser($modelClass, $user);

        if (! $model instanceof Authenticatable) {
            return null;
        }

        FaydaIdentity::query()->create([
            'user_id' => $model->getAuthIdentifier(),
            'subject' => $user->sub,
            'client_id' => $clientId,
            'claims' => $user->claims,
            'last_login_at' => now(),
        ]);

        return $model;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function findVerifiedEmailUser(string $modelClass, FaydaUser $user): ?Model
    {
        if ($user->email === null || $user->emailVerified !== true) {
            return null;
        }

        /** @var Model|null $model */
        $model = $modelClass::query()->where('email', $user->email)->first();

        return $model;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function createUser(string $modelClass, FaydaUser $user): Model
    {
        /** @var Model $model */
        $model = new $modelClass;

        $name = $user->name;

        if ($name === null || $name === '') {
            $name = trim(($user->givenName ?? '').' '.($user->familyName ?? ''));
        }

        if ($name === '') {
            $name = $user->preferredUsername ?? 'Fayda User';
        }

        $attributes = [
            'name' => $name,
            'email' => $user->email,
            'password' => Hash::make(Str::random(40)),
        ];

        $model->forceFill($attributes)->save();

        return $model;
    }
}
