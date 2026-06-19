<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

beforeEach(function (): void {
    Cache::flush();
});

test('it caches the resolved connection between permission checks', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['view']);

    Cache::flush();
    $key = $user->connectionCacheKey($team);

    expect(Cache::has($key))->toBeFalse();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();

    expect(Cache::has($key))->toBeTrue();
});

test('it can still force a fresh permission lookup', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['view']);

    // Prime the cache.
    expect($user->hasPermissionThroughConnection($team, 'edit'))->toBeFalse();

    // Mutate the row directly, bypassing the package so the cache goes stale.
    Connection::query()->update(['permissions' => collect(['view', 'edit'])]);

    expect($user->hasPermissionThroughConnection($team, 'edit'))->toBeFalse()
        ->and($user->hasPermissionThroughConnection($team, 'edit', force: true))->toBeTrue();
});

test('it returns false when there is no connection at all', function (): void {
    $user = User::create();
    $team = Team::create();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeFalse();
});

test('connectTo creates a connection with permissions and expiry', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, ['view', 'edit'], now()->addDay());

    expect($connection)
        ->toBeInstanceOf(Connection::class)
        ->and($connection->hasPermission('view'))->toBeTrue()
        ->and($connection->expires_at)->not->toBeNull();
});

test('connectTo accepts a collection of permissions', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, collect(['publish']));

    expect($connection->hasPermission('publish'))->toBeTrue();
});

test('disconnectFrom removes the connection', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['view']);

    expect($user->isConnectedTo($team))->toBeTrue();

    $user->disconnectFrom($team);

    expect($user->isConnectedTo($team))->toBeFalse();
});

test('disconnectFrom throws when there is no connection', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->disconnectFrom($team);
})->throws(ConnectionNotFound::class);

test('grantThroughConnection adds permissions, creating the connection when absent', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->grantThroughConnection($team, 'view');
    $user->grantThroughConnection($team, 'edit');

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($team, 'edit'))->toBeTrue();
});

test('revokeThroughConnection removes a permission', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['view', 'edit']);
    $user->revokeThroughConnection($team, 'edit');

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($team, 'edit'))->toBeFalse();
});

test('syncConnectionPermissions replaces the whole permission set', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['view', 'edit']);
    $user->syncConnectionPermissions($team, ['publish']);

    expect($user->permissionsThroughConnection($team)->all())->toBe(['publish']);
});

test('permissionsThroughConnection returns an empty collection without a connection', function (): void {
    $user = User::create();
    $team = Team::create();

    expect($user->permissionsThroughConnection($team)->all())->toBe([]);
});
