<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, MED): permissions()->clear() on a pair with no connection
 * created an accepted one — a "remove all permissions" call made the user a member.
 */

test('clear() on a pair with no connection throws and creates nothing', function (): void {
    $user = User::create();
    $team = Team::create();

    expect(fn () => Connections::between($user, $team)->permissions()->clear())
        ->toThrow(ConnectionNotFound::class);

    expect(Connection::query()->withTrashed()->count())->toBe(0)
        ->and($user->isConnectedTo($team))->toBeFalse();
});

test('clearConnectionPermissions() on a soft-deleted pair throws and keeps it deleted', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->connect();
    Connections::between($user, $team)->disconnect();

    expect(fn () => $user->clearConnectionPermissions($team))->toThrow(ConnectionNotFound::class);

    expect(Connection::query()->onlyTrashed()->count())->toBe(1)
        ->and($user->isConnectedTo($team))->toBeFalse();
});

test('clear() empties an existing connection without touching its status', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view', 'edit')->invite();

    $connection = Connections::between($user, $team)->permissions()->clear();

    expect($connection->permissions->all())->toBe([])
        ->and($connection->isPending())->toBeTrue();
});
