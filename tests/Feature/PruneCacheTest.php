<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, LOW): prune() did not invalidate the in-request permission
 * cache, so with enforce_active_on_check off a cached, now soft-deleted row kept granting.
 */

test('prune() invalidates the cache', function (): void {
    config()->set('connections.enforce_active_on_check', false);
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('admin')->expiresIn(now()->subMinute())->connect();

    expect($user->hasPermissionThroughConnection($team, 'admin'))->toBeTrue();

    expect(Connections::prune())->toBe(1)
        ->and($user->hasPermissionThroughConnection($team, 'admin'))->toBeFalse();
});
