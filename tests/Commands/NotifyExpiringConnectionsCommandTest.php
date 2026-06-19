<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Events\ConnectionExpiring;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

afterEach(fn () => Carbon::setTestNow());

test('it dispatches an event for each connection expiring within the window', function (): void {
    Carbon::setTestNow('2026-06-19 12:00:00');

    $user = User::create();
    $soon = Team::create();
    $later = Team::create();

    Connections::between($user, $soon)->expiringAt(now()->addDays(3))->connect();
    Connections::between($user, $later)->expiringAt(now()->addDays(30))->connect();

    Event::fake();

    $this->artisan('connections:notify-expiring', ['--days' => 7])
        ->assertSuccessful();

    Event::assertDispatched(ConnectionExpiring::class, 1);
});

test('it reports the days until expiry on the event', function (): void {
    Carbon::setTestNow('2026-06-19 12:00:00');

    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->expiringAt(now()->addDays(3))->connect();

    Event::fake();

    $this->artisan('connections:notify-expiring')->assertSuccessful();

    Event::assertDispatched(ConnectionExpiring::class, function (ConnectionExpiring $event): bool {
        return $event->daysUntilExpiry === 3;
    });
});

test('it succeeds with no expiring connections', function (): void {
    Event::fake();

    $this->artisan('connections:notify-expiring')->assertSuccessful();

    Event::assertNotDispatched(ConnectionExpiring::class);
});
