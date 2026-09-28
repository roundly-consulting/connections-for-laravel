<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, MED): revokeAll() skipped pending / blocked / expired
 * connections, so a revoked permission came back on accept / extend.
 */

test('revokeAll() reaches a pending connection so the permission stays revoked on accept', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('publish')->invite();

    $revoked = Connections::from($user)->toMany([$team])->revokeAll('publish');
    $team->acceptConnectionFrom($user);

    expect($revoked)->toHaveCount(1)
        ->and($user->hasPermissionThroughConnection($team, 'publish', force: true))->toBeFalse();
});

test('revokeAll() reaches blocked and expired connections', function (): void {
    $user = User::create();
    $blocked = Team::create();
    $expired = Team::create();
    Connections::between($user, $blocked)->withPermissions('publish')->connect();
    $blocked->blockConnectionFrom($user);
    Connections::between($user, $expired)->withPermissions('publish')->expiresIn(now()->subMinute())->connect();

    Connections::from($user)->toMany([$blocked, $expired])->revokeAll('publish');

    expect(Connections::between($user, $blocked)->find()?->permissions->all())->toBe([])
        ->and(Connections::between($user, $expired)->find()?->permissions->all())->toBe([]);
});

test('revokeAll() still skips a pair with no connection or a soft-deleted one', function (): void {
    $user = User::create();
    $missing = Team::create();
    $trashed = Team::create();
    Connections::between($user, $trashed)->withPermissions('publish')->connect();
    Connections::between($user, $trashed)->disconnect();

    $revoked = Connections::from($user)->toMany([$missing, $trashed])->revokeAll('publish');

    expect($revoked)->toBeEmpty()
        ->and(Connection::query()->withTrashed()->count())->toBe(1);
});
