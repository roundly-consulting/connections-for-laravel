<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

beforeEach(function () {
    Cache::flush();
});

test('it caches the resolved connection between permission checks', function () {
    $user = User::create();
    $team = Team::create();

    (new CreateConnection)->execute($user, $team, collect(['view']));

    $key = $user->connectionCacheKey($team);

    expect(Cache::has($key))->toBeFalse();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();

    expect(Cache::has($key))->toBeTrue();
});

test('it can force a fresh permission lookup', function () {
    $user = User::create();
    $team = Team::create();

    (new CreateConnection)->execute($user, $team, collect(['view']));

    expect($user->hasPermissionThroughConnection($team, 'edit'))->toBeFalse();

    (new CreateConnection)->execute($user, $team, collect(['view', 'edit']));

    expect($user->hasPermissionThroughConnection($team, 'edit'))->toBeFalse()
        ->and($user->hasPermissionThroughConnection($team, 'edit', force: true))->toBeTrue();
});

test('it returns false when there is no connection at all', function () {
    $user = User::create();
    $team = Team::create();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeFalse();
});
