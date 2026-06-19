<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\SyncPermissions;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it auto-creates the connection when none exists', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = app(SyncPermissions::class)->execute($user, $team, 'view', 'edit');

    expect($connection->permissions->all())->toBe(['view', 'edit']);
});

test('it replaces the exact permission set', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view', 'edit']));

    $connection = app(SyncPermissions::class)->execute($user, $team, 'publish');

    expect($connection->permissions->all())->toBe(['publish']);
});

test('syncing to an empty set clears all permissions', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    $connection = app(SyncPermissions::class)->execute($user, $team);

    expect($connection->permissions->all())->toBe([]);
});
