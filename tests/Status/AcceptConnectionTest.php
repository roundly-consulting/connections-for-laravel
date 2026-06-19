<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Actions\AcceptConnection;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionAccepted;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it accepts a pending connection and dispatches the event', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    Event::fake();

    $connection = app(AcceptConnection::class)->execute($user, $team);

    expect($connection->status)->toBe(ConnectionStatus::Accepted)
        ->and($connection->isActive())->toBeTrue();

    Event::assertDispatched(ConnectionAccepted::class);
});

test('accepting an already-accepted connection is a no-op without an event', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    Event::fake();

    app(AcceptConnection::class)->execute($user, $team);

    Event::assertNotDispatched(ConnectionAccepted::class);
});

test('accepting un-blocks a blocked connection', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();
    Connections::between($user, $team)->block();

    $connection = app(AcceptConnection::class)->execute($user, $team);

    expect($connection->status)->toBe(ConnectionStatus::Accepted);
});

test('it throws when the connection is missing', function (): void {
    $user = User::create();
    $team = Team::create();

    app(AcceptConnection::class)->execute($user, $team);
})->throws(ConnectionNotFound::class);

test('the event is suppressed when events are disabled', function (): void {
    config()->set('connections.events.enabled', false);

    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    Event::fake();

    app(AcceptConnection::class)->execute($user, $team);

    Event::assertNotDispatched(ConnectionAccepted::class);
});
