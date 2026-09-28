<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review):
 *  MED: the in-request permission cache was a process-wide static, so under Octane (or any
 *  long-lived worker) a permission revoked elsewhere kept authorizing on later requests. It is
 *  now a container-scoped instance, dropped whenever Laravel starts a new request / job
 *  lifecycle (Octane and the queue worker both call forgetScopedInstances()).
 */

function revokeBehindTheCache(User $user, Team $team): void
{
    DB::table('connections')
        ->where('connectable_id', $team->id)
        ->where('connectable_type', $team->getMorphClass())
        ->update(['permissions' => '[]']);
}

test('a cached permission is dropped when a new request / job lifecycle starts', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('admin')->connect();

    expect($user->hasPermissionThroughConnection($team, 'admin'))->toBeTrue();

    revokeBehindTheCache($user, $team);

    // Same lifecycle: the memoised connection still answers (that is the cache's job).
    expect($user->hasPermissionThroughConnection($team, 'admin'))->toBeTrue();

    // What Octane (per request) and the queue worker (per job) do between lifecycles.
    app()->forgetScopedInstances();

    expect(Cache::has(Cache::keyFor($user, $team)))->toBeFalse()
        ->and($user->hasPermissionThroughConnection($team, 'admin'))->toBeFalse();
});

test('a fresh application never sees another application\'s cache', function (): void {
    Cache::put('connections-test-key', 'stale');

    expect(Cache::has('connections-test-key'))->toBeTrue();

    // The next request on a worker that builds a new application per request.
    Container::setInstance(new Container);

    try {
        expect(Cache::has('connections-test-key'))->toBeFalse();
    } finally {
        Container::setInstance($this->app);
    }

    expect(Cache::has('connections-test-key'))->toBeTrue();
});

test('the cache is one instance per lifecycle', function (): void {
    expect(app(Cache::class))->toBe(app(Cache::class));

    $before = app(Cache::class);
    app()->forgetScopedInstances();

    expect(app(Cache::class))->not->toBe($before);
});
