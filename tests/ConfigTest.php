<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it ships sensible config defaults', function (): void {
    expect(config('connections.model'))->toBe(Connection::class)
        ->and(config('connections.table'))->toBe('connections')
        ->and(config('connections.cache.enabled'))->toBeTrue()
        ->and(config('connections.events.enabled'))->toBeTrue()
        ->and(config('connections.register_gate'))->toBeFalse();
});

// 'a custom connection model is honoured' now lives in tests/ModelSwap/, as the shipped
// `toHonourModelSwap` proof. Both halves of the version that was here were too weak to
// catch the bugs this class of test exists for: a runtime `config()->set()` leaves every
// listener and relation the provider wired at boot on the packaged Connection (media #28),
// and `instanceof` passes for a row created as the packaged class, which never fires the
// host's model events (permissions #31). The replacement swaps before boot and asserts the
// concrete class plus a counted `created` event.
//
// The fall-back cases below stay: they assert this package's *documented* contract that a
// misconfigured model never takes an app down, which is domain behaviour, not machinery.

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
