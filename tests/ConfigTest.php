<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\CustomConnection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it ships sensible config defaults', function (): void {
    expect(config('connections.model'))->toBe(Connection::class)
        ->and(config('connections.table'))->toBe('connections')
        ->and(config('connections.cache.enabled'))->toBeTrue()
        ->and(config('connections.events.enabled'))->toBeTrue()
        ->and(config('connections.register_gate'))->toBeFalse();
});

test('a custom connection model is honoured', function (): void {
    config()->set('connections.model', CustomConnection::class);

    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, ['view']);

    expect($connection)->toBeInstanceOf(CustomConnection::class)
        ->and($user->connections()->first())->toBeInstanceOf(CustomConnection::class);
});

test('an invalid model config falls back to the default model', function (): void {
    config()->set('connections.model', 'not-a-class');

    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, ['view']);

    expect($connection)->toBeInstanceOf(Connection::class);
});

test('the trait relation falls back to the default model when config is invalid', function (): void {
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['view']);

    config()->set('connections.model', 'not-a-class');

    expect($user->connections()->first())->toBeInstanceOf(Connection::class);
});
