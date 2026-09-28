<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (found while fixing the 2026-09-28 review): config values arrive from env() as
 * strings. `CONNECTIONS_EXPIRY_DEFAULT=3600` (documented as "seconds") crashed every connect
 * in Carbon, and a boolean flag set to "off" / "no" read as true — so
 * `CONNECTIONS_REGISTER_GATE=no` switched the gate ON.
 */

test('a numeric-string default expiry is read as seconds', function (): void {
    Carbon::setTestNow('2026-09-28 12:00:00');
    config()->set('connections.expiry.default', '3600');

    $connection = Connections::between(User::create(), Team::create())->connect();

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-09-28 13:00:00');

    $this->artisan('about --only=connections')->expectsOutputToContain('3600s')->assertExitCode(0);
});

test('boolean flags accept off / no strings', function (string $off): void {
    config()->set('connections.enforce_active_on_check', $off);
    config()->set('connections.events.enabled', $off);
    config()->set('connections.cache.enabled', $off);
    config()->set('connections.register_gate', $off);
    $user = User::create();
    $team = Team::create();

    Event::fake();
    Connections::between($user, $team)->withPermissions('view')->invite();

    // Advisory checks: the pending link counts once enforcement is off.
    expect($user->isConnectedTo($team))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();

    Event::assertNotDispatched(ConnectionCreated::class);

    $this->artisan('about --only=connections')
        ->expectsOutputToContain('ADVISORY')
        ->doesntExpectOutputToContain(' ON')
        ->assertExitCode(0);
})->with(['off', 'no', 'false', '0']);

test('boolean flags accept on / yes strings', function (string $on): void {
    config()->set('connections.enforce_active_on_check', $on);
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    expect($user->isConnectedTo($team))->toBeFalse();
})->with(['on', 'yes', 'true', '1']);
