<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\ExtendConnection;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it sets a new expiry', function (): void {
    Carbon::setTestNow('2026-01-01 10:00:00');

    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team);

    $connection = app(ExtendConnection::class)->execute($user, $team, now()->addWeek());

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-01-08 10:00:00');

    Carbon::setTestNow();
});

test('it clears expiry when passed null', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, null, now()->addDay());

    $connection = app(ExtendConnection::class)->execute($user, $team, null);

    expect($connection->expires_at)->toBeNull();
});

test('it throws when there is no connection', function (): void {
    $user = User::create();
    $team = Team::create();

    app(ExtendConnection::class)->execute($user, $team, now()->addDay());
})->throws(ConnectionNotFound::class);
