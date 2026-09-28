<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it dispatches ConnectionCreated on first connect', function (): void {
    Event::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::assertDispatched(ConnectionCreated::class);
});

test('it dispatches ConnectionUpdated when re-connecting', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::fake();

    Connections::between($user, $team)->withPermissions('view')->connect();

    Event::assertDispatched(ConnectionUpdated::class);
});

test('it dispatches ConnectionRemoved on disconnect', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::fake();

    Connections::between($user, $team)->disconnect();

    Event::assertDispatched(ConnectionRemoved::class);
});

test('it dispatches ConnectionPermissionsChanged on grant', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::fake();

    Connections::between($user, $team)->permissions()->grant('view');

    Event::assertDispatched(ConnectionPermissionsChanged::class, function (ConnectionPermissionsChanged $event): bool {
        return $event->previous === [] && $event->current === ['view'];
    });
});

test('it does not dispatch a permission event when nothing changed', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    Event::fake();

    Connections::between($user, $team)->permissions()->grant('view');

    Event::assertNotDispatched(ConnectionPermissionsChanged::class);
});

test('events can be disabled via config', function (): void {
    config()->set('connections.events.enabled', false);

    Event::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::assertNotDispatched(ConnectionCreated::class);
});
