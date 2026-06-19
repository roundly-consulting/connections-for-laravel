<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionInvited;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('invite creates a pending connection and dispatches ConnectionInvited', function (): void {
    $user = User::create();
    $team = Team::create();

    Event::fake();

    $connection = Connections::between($user, $team)->invite();

    expect($connection->isPending())->toBeTrue();

    Event::assertDispatched(ConnectionInvited::class);
});

test('a plain connect does not dispatch ConnectionInvited', function (): void {
    $user = User::create();
    $team = Team::create();

    Event::fake();

    Connections::between($user, $team)->connect();

    Event::assertNotDispatched(ConnectionInvited::class);
});

test('asPending stages the pending status on the builder', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->asPending()->connect();

    expect($connection->status)->toBe(ConnectionStatus::Pending);
});

test('the receiving side accepts an invitation', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    $connection = $team->acceptConnectionFrom($user);

    expect($connection->isAccepted())->toBeTrue();
});

test('the receiving side blocks a connection', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    $connection = $team->blockConnectionFrom($user);

    expect($connection->isBlocked())->toBeTrue();
});

test('inviteConnection on the trait creates a pending link', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = $user->inviteConnection($team);

    expect($connection->isPending())->toBeTrue();
});

test('the default status comes from config', function (): void {
    config()->set('connections.default_status', 'pending');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->isPending())->toBeTrue();
});

test('an invalid default status falls back to accepted', function (): void {
    config()->set('connections.default_status', 'nonsense');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->isAccepted())->toBeTrue();
});
