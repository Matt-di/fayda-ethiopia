<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mattdi\Fayda\Laravel\Auth\EloquentUserResolver;
use Mattdi\Fayda\Laravel\Models\FaydaIdentity;
use Mattdi\Fayda\Tests\Support\User;

beforeEach(function (): void {
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('fayda_identities', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('subject');
        $table->string('client_id')->nullable();
        $table->json('claims')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->timestamps();
        $table->unique(['subject', 'client_id']);
    });

    config([
        'fayda.client.id' => 'client-1',
        'fayda.persistence.enabled' => true,
        'fayda.persistence.user_model' => User::class,
        'fayda.persistence.table' => 'fayda_identities',
        'fayda.persistence.create_users' => false,
    ]);
});

it('does not create users unless explicitly enabled', function (): void {
    $resolver = new EloquentUserResolver;

    expect($resolver->resolve(faydaUser()))->toBeNull()
        ->and(User::query()->count())->toBe(0);
});

it('creates and links a user when create_users is enabled', function (): void {
    config(['fayda.persistence.create_users' => true]);

    $resolver = new EloquentUserResolver;
    $user = $resolver->resolve(faydaUser());

    expect($user)->toBeInstanceOf(User::class)
        ->and($user?->name)->toBe('Abebe Bikila')
        ->and($user?->email)->toBe('abebe@example.test')
        ->and(User::query()->count())->toBe(1)
        ->and(FaydaIdentity::query()->count())->toBe(1);

    $again = $resolver->resolve(faydaUser());

    expect($again?->getAuthIdentifier())->toBe($user?->getAuthIdentifier())
        ->and(User::query()->count())->toBe(1)
        ->and(FaydaIdentity::query()->count())->toBe(1);
});

it('links an existing user when the email is verified', function (): void {
    config(['fayda.persistence.create_users' => true]);

    $existing = User::query()->create([
        'name' => 'Existing User',
        'email' => 'abebe@example.test',
        'password' => bcrypt('secret'),
    ]);

    $user = (new EloquentUserResolver)->resolve(faydaUser());

    expect($user?->getAuthIdentifier())->toBe($existing->getAuthIdentifier())
        ->and(User::query()->count())->toBe(1);
});

it('creates the identity table through the published migration', function (): void {
    Schema::dropIfExists('fayda_identities');

    /** @var Migration $migration */
    $migration = require __DIR__.'/../../database/migrations/2026_01_01_000000_create_fayda_identities_table.php';
    $migration->up();

    expect(Schema::hasTable('fayda_identities'))->toBeTrue();

    $migration->down();

    expect(Schema::hasTable('fayda_identities'))->toBeFalse();
});
