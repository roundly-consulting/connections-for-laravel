<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Events\ConnectionRestored;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('toggle connects when no connection exists and disconnects when one does', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->toggle();
    expect($connection)->toBeInstanceOf(Connection::class)
        ->and($user->fresh()->isConnectedTo($team))->toBeTrue();

    $result = Connections::between($user, $team)->toggle();
    expect($result)->toBeNull()
        ->and($user->fresh()->isConnectedTo($team))->toBeFalse();
});

test('toggleConnection on the trait mirrors the builder', function (): void {
    $user = User::create();
    $team = Team::create();

    expect($user->toggleConnection($team))->toBeInstanceOf(Connection::class)
        ->and($user->fresh()->toggleConnection($team))->toBeNull();
});

test('reconnect restores a soft-deleted connection and fires ConnectionRestored', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->connect();
    Connections::between($user, $team)->disconnect();

    Event::fake();

    $connection = Connections::between($user, $team)->reconnect();

    expect($connection->trashed())->toBeFalse()
        ->and($connection->hasPermission('view'))->toBeTrue()
        ->and(Connection::query()->count())->toBe(1);

    Event::assertDispatched(ConnectionRestored::class);
});

test('restore is an alias of reconnect', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();
    Connections::between($user, $team)->disconnect();

    $connection = Connections::between($user, $team)->restore();

    expect($connection->trashed())->toBeFalse();
});

test('reconnect with nothing trashed behaves like connect', function (): void {
    $user = User::create();
    $team = Team::create();

    Event::fake();

    $connection = Connections::between($user, $team)->reconnect();

    expect($connection->trashed())->toBeFalse()
        ->and($user->fresh()->isConnectedTo($team))->toBeTrue();

    Event::assertNotDispatched(ConnectionRestored::class);
});

test('reconnectTo on the trait restores the trashed link', function (): void {
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team);
    $user->disconnectFrom($team);

    $connection = $user->reconnectTo($team);

    expect($connection->trashed())->toBeFalse();
});
