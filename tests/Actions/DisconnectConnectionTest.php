<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\DisconnectConnection;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it soft-deletes the connection', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    app(DisconnectConnection::class)->execute($user, $team);

    expect($user->isConnectedTo($team))->toBeFalse()
        ->and(Connection::withTrashed()->count())->toBe(1);
});

test('it throws when there is no connection', function (): void {
    $user = User::create();
    $team = Team::create();

    app(DisconnectConnection::class)->execute($user, $team);
})->throws(ConnectionNotFound::class);
