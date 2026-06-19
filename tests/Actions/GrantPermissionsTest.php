<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it auto-creates the connection when none exists', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = app(GrantPermissions::class)->execute($user, $team, 'view');

    expect($connection->hasPermission('view'))->toBeTrue();
});

test('it adds a new permission to an existing connection', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    $connection = app(GrantPermissions::class)->execute($user, $team, 'edit');

    expect($connection->permissions->all())->toBe(['view', 'edit']);
});

test('granting an existing permission is a net no-op', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    $connection = app(GrantPermissions::class)->execute($user, $team, 'view');

    expect($connection->permissions->all())->toBe(['view']);
});
