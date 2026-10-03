<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

afterEach(fn () => Carbon::setTestNow());

test('default permissions apply when none are supplied', function (): void {
    config()->set('connections.default_permissions', ['view']);

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->permissions->all())->toBe(['view']);
});

test('an explicit empty permission set stays empty', function (): void {
    config()->set('connections.default_permissions', ['view']);

    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, []);

    expect($connection->permissions->all())->toBe([]);
});

test('explicit permissions win over the default', function (): void {
    config()->set('connections.default_permissions', ['view']);

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->withPermissions('edit')->connect();

    expect($connection->permissions->all())->toBe(['edit']);
});

test('the default expiry as a relative string is applied', function (): void {
    Carbon::setTestNow('2026-06-19 12:00:00');
    config()->set('connections.expiry.default', '30 days');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->expires_at->toDateString())->toBe('2026-07-19');
});

test('the default expiry as an integer of seconds is applied', function (): void {
    Carbon::setTestNow('2026-06-19 12:00:00');
    config()->set('connections.expiry.default', 3600);

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->expires_at->equalTo(now()->addHour()))->toBeTrue();
});

test('an explicit expiry wins over the default', function (): void {
    Carbon::setTestNow('2026-06-19 12:00:00');
    config()->set('connections.expiry.default', '30 days');

    $user = User::create();
    $team = Team::create();
    $explicit = now()->addDay();

    $connection = Connections::between($user, $team)->expiringAt($explicit)->connect();

    expect($connection->expires_at->toDateTimeString())->toBe($explicit->toDateTimeString());
});

test('no default expiry leaves the connection without one', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->expires_at)->toBeNull();
});

test('a non-array default_permissions config throws (strict config)', function (): void {
    config()->set('connections.default_permissions', 'not-an-array');

    $user = User::create();
    $team = Team::create();

    expect(fn () => Connections::between($user, $team)->connect())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [connections.default_permissions] must be a list of permission names, [not-an-array] given.');
});

test('a blank-string default expiry means no default expiry', function (): void {
    config()->set('connections.expiry.default', '   ');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->expires_at)->toBeNull();
});
