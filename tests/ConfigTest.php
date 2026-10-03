<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

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
// A misconfigured model stops the app with a message naming the key: silently swapping in
// the packaged model would hide the host's mistake behind wrong-class rows.

test('an invalid model config throws instead of falling back to the default model', function (): void {
    config()->set('connections.model', 'not-a-class');

    $user = User::create();
    $team = Team::create();

    expect(fn (): Connection => $user->connectTo($team, ['view']))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [connections.model] must be a class-string of ['.Connection::class.'], [not-a-class] given.',
    );
});

test('the trait relation throws when the model config is invalid', function (): void {
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['view']);

    config()->set('connections.model', 'not-a-class');

    expect(fn (): mixed => $user->connections()->first())->toThrow(InvalidConfigurationException::class, '[connections.model]');
});
