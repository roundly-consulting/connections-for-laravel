<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

beforeEach(function (): void {
    Cache::flush();
});

test('a grant is visible without forcing a fresh lookup', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    // Prime the cache with a miss.
    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeFalse();

    Connections::between($user, $team)->grant('view');

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();
});

test('a disconnect is visible without forcing a fresh lookup', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();

    Connections::between($user, $team)->disconnect();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeFalse();
});

test('with cache disabled every check re-queries', function (): void {
    config()->set('connections.cache.enabled', false);

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    expect(Cache::enabled())->toBeFalse()
        ->and($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();

    // No key should have been stored when the cache is disabled.
    expect(Cache::has($user->connectionCacheKey($team)))->toBeFalse();
});
