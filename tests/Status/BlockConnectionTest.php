<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Actions\BlockConnection;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionBlocked;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it blocks an accepted connection and dispatches the event', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    Event::fake();

    $connection = app(BlockConnection::class)->execute($user, $team);

    expect($connection->status)->toBe(ConnectionStatus::Blocked)
        ->and($connection->isBlocked())->toBeTrue()
        ->and($connection->isActive())->toBeFalse();

    Event::assertDispatched(ConnectionBlocked::class);
});

test('blocking an already-blocked connection is a no-op without an event', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();
    Connections::between($user, $team)->block();

    Event::fake();

    app(BlockConnection::class)->execute($user, $team);

    Event::assertNotDispatched(ConnectionBlocked::class);
});

test('it throws when the connection is missing', function (): void {
    $user = User::create();
    $team = Team::create();

    app(BlockConnection::class)->execute($user, $team);
})->throws(ConnectionNotFound::class);
