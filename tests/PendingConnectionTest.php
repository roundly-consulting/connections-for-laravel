<?php

declare(strict_types=1);

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Exceptions\MissingConnectable;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it connects, grants, revokes, syncs, checks and disconnects fluently', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)
        ->withPermissions('view', 'edit')
        ->connect();

    expect(Connections::between($user, $team)->permissions()->has('view'))->toBeTrue()
        ->and(Connections::between($user, $team)->exists())->toBeTrue();

    Connections::between($user, $team)->permissions()->grant('publish');
    expect(Connections::between($user, $team)->permissions()->has('publish'))->toBeTrue();

    Connections::between($user, $team)->permissions()->revoke('publish');
    expect(Connections::between($user, $team)->permissions()->has('publish'))->toBeFalse();

    Connections::between($user, $team)->permissions()->sync('view');
    expect(Connections::between($user, $team)->permissions()->has('edit'))->toBeFalse();

    Connections::between($user, $team)->disconnect();
    expect(Connections::between($user, $team)->exists())->toBeFalse();
});

test('from() defers the connectable until to()', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::from($user)->to($team)->withPermissions('view')->connect();

    expect(Connections::between($user, $team)->permissions()->has('view'))->toBeTrue();
});

test('expiringAt sets the expiry', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)
        ->expiringAt(now()->addDay())
        ->connect();

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-01-02 00:00:00');

    Carbon::setTestNow();
});

test('expiresIn accepts seconds', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->expiresIn(3600)->connect();

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-01-01 01:00:00');

    Carbon::setTestNow();
});

test('expiresIn accepts a CarbonInterval', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)
        ->expiresIn(CarbonInterval::days(2))
        ->connect();

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-01-03 00:00:00');

    Carbon::setTestNow();
});

test('expiresIn accepts a CarbonInterface instant', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)
        ->expiresIn(now()->addHours(5))
        ->connect();

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-01-01 05:00:00');

    Carbon::setTestNow();
});

test('extend updates the expiry through the builder', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();
    $connection = Connections::between($user, $team)->extend(now()->addDay());

    expect($connection->expires_at?->toDateTimeString())->toBe('2026-01-02 00:00:00');

    Carbon::setTestNow();
});

test('terminal verbs throw without a connectable', function (): void {
    $user = User::create();

    Connections::from($user)->connect();
})->throws(MissingConnectable::class);
