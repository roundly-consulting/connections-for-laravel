<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it removes a present permission', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view', 'edit']));

    $connection = app(RevokePermissions::class)->execute($user, $team, 'edit');

    expect($connection->permissions->all())->toBe(['view']);
});

test('revoking an absent permission is a no-op', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    $connection = app(RevokePermissions::class)->execute($user, $team, 'edit');

    expect($connection->permissions->all())->toBe(['view']);
});

test('it throws when there is no connection', function (): void {
    $user = User::create();
    $team = Team::create();

    app(RevokePermissions::class)->execute($user, $team, 'view');
})->throws(ConnectionNotFound::class);
