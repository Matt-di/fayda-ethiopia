<?php

declare(strict_types=1);

use Mattdi\Fayda\Core\Dto\Address;
use Mattdi\Fayda\Core\Dto\FaydaUser;

it('maps Fayda claims to a typed user', function (): void {
    $user = FaydaUser::fromClaims(faydaUserInfoClaims());

    expect($user->sub)->toBe('fayda-subject-1')
        ->and($user->name)->toBe('Abebe Bikila')
        ->and($user->email)->toBe('abebe@example.test')
        ->and($user->emailVerified)->toBeTrue()
        ->and($user->phoneNumber)->toBe('+251911000000')
        ->and($user->gender)->toBe('male')
        ->and($user->birthdate)->toBe('1990-01-01')
        ->and($user->nationality)->toBe('ETH')
        ->and($user->address)->toBeInstanceOf(Address::class)
        ->and($user->address?->region)->toBe('Addis Ababa')
        ->and($user->address?->zone)->toBe('Bole')
        ->and($user->address?->woreda)->toBe('03');
});

it('accepts a phone claim alias and a string address', function (): void {
    $user = FaydaUser::fromClaims([
        'sub' => 'subject-2',
        'phone' => '+251900000000',
        'address' => 'Addis Ababa, Ethiopia',
    ]);

    expect($user->phoneNumber)->toBe('+251900000000')
        ->and($user->address?->formatted)->toBe('Addis Ababa, Ethiopia');
});

it('filters null values from the array representation', function (): void {
    $user = FaydaUser::fromClaims(['sub' => 'subject-3', 'name' => 'Test User']);

    expect($user->toArray())->toBe([
        'sub' => 'subject-3',
        'name' => 'Test User',
    ]);
});

it('exposes raw claims through claim()', function (): void {
    $user = FaydaUser::fromClaims(['sub' => 'subject-4', 'custom' => 'value']);

    expect($user->claim('custom'))->toBe('value')
        ->and($user->claim('missing', 'fallback'))->toBe('fallback');
});
