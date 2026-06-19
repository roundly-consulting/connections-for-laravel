<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

afterEach(fn () => Carbon::setTestNow());

test('activeConnections excludes pending, blocked and expired links', function (): void {
    $user = User::create();
    $accepted = Team::create();
    $pending = Team::create();
    $blocked = Team::create();
    $expired = Team::create();

    Connections::between($user, $accepted)->connect();
    Connections::between($user, $pending)->invite();
    Connections::between($user, $blocked)->connect();
    Connections::between($user, $blocked)->block();
    Connections::between($user, $expired)->expiringAt(now()->subDay())->connect();

    $active = $user->activeConnections()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->connectable_id)->toBe($accepted->getKey());
});

test('expiredConnections returns only past-expiry links', function (): void {
    $user = User::create();
    $expired = Team::create();
    $live = Team::create();

    Connections::between($user, $expired)->expiringAt(now()->subDay())->connect();
    Connections::between($user, $live)->expiringAt(now()->addDay())->connect();

    $rows = $user->expiredConnections()->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->connectable_id)->toBe($expired->getKey());
});

test('expiringConnections respects the day boundary', function (): void {
    Carbon::setTestNow('2026-06-19 12:00:00');

    $user = User::create();
    $soon = Team::create();
    $later = Team::create();

    Connections::between($user, $soon)->expiringAt(now()->addDays(3))->connect();
    Connections::between($user, $later)->expiringAt(now()->addDays(30))->connect();

    expect($user->expiringConnections(7)->get())->toHaveCount(1)
        ->and($user->expiringConnections(31)->get())->toHaveCount(2);
});

test('connectionsWithPermission filters by stored permission', function (): void {
    $user = User::create();
    $a = Team::create();
    $b = Team::create();

    Connections::between($user, $a)->withPermissions('publish')->connect();
    Connections::between($user, $b)->withPermissions('view')->connect();

    $rows = $user->connectionsWithPermission('publish')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->connectable_id)->toBe($a->getKey());
});
