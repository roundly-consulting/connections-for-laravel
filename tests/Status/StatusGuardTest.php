<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, CRITICAL): re-connecting rewrote `status`, so a blocked
 * connector unblocked itself and an inviter self-accepted. connect / invite / toggle / sync
 * now keep an existing row's status unless one is passed explicitly, never lift a block, and
 * every explicit transition goes through ConnectionStatus::canTransitionTo().
 */

function statusGuardBlockedPair(): array
{
    $spammer = User::create();
    $team = Team::create();
    Connections::between($spammer, $team)->withPermissions('view')->connect();
    $team->blockConnectionFrom($spammer);

    return [$spammer, $team];
}

test('a blocked connector cannot toggle itself back in', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    $connection = $spammer->toggleConnection($team);

    expect($connection?->status)->toBe(ConnectionStatus::Blocked)
        ->and($team->hasConnector($spammer))->toBeFalse()
        ->and(Connections::between($spammer, $team)->find()?->isActive())->toBeFalse();
});

test('a blocked connector cannot connectTo past the block', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    $connection = $spammer->connectTo($team, ['post']);

    expect($connection->status)->toBe(ConnectionStatus::Blocked)
        ->and($spammer->hasPermissionThroughConnection($team, 'post', force: true))->toBeFalse();
});

test('a blocked connector cannot re-invite over the block', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    expect(fn () => $spammer->inviteConnection($team))
        ->toThrow(InvalidStatusTransition::class);

    expect(Connections::between($spammer, $team)->find()?->status)->toBe(ConnectionStatus::Blocked);
});

test('a sync overlap keeps a blocked connection blocked', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    Connections::from($spammer)->sync([$team]);

    expect(Connections::between($spammer, $team)->find()?->status)->toBe(ConnectionStatus::Blocked);
});

test('a connectAll over a blocked target keeps it blocked', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    Connections::from($spammer)->toMany([$team])->connectAll();

    expect(Connections::between($spammer, $team)->find()?->status)->toBe(ConnectionStatus::Blocked);
});

test('a block survives disconnect and a fresh connect', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    $spammer->disconnectFrom($team);
    $connection = $spammer->connectTo($team);

    expect($connection->status)->toBe(ConnectionStatus::Blocked)
        ->and($connection->trashed())->toBeFalse()
        ->and(Connection::query()->withTrashed()->count())->toBe(1)
        ->and($team->hasConnector($spammer))->toBeFalse();
});

test('a block survives disconnect and a re-invite', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    $spammer->disconnectFrom($team);

    expect(fn () => $spammer->inviteConnection($team))->toThrow(InvalidStatusTransition::class);
    expect(Connection::query()->onlyTrashed()->count())->toBe(1);
});

test('the raw action refuses to lift a block with an explicit status', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    expect(fn () => app(CreateConnection::class)->execute($spammer, $team, status: ConnectionStatus::Accepted))
        ->toThrow(InvalidStatusTransition::class, 'blocked');

    expect(Connections::between($spammer, $team)->find()?->status)->toBe(ConnectionStatus::Blocked);
});

test('accept() is the explicit unblock', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    $team->acceptConnectionFrom($spammer);

    expect(Connections::between($spammer, $team)->find()?->status)->toBe(ConnectionStatus::Accepted);
});

test('an inviter cannot self-accept by re-connecting', function (): void {
    $requester = User::create();
    $team = Team::create();
    Connections::between($requester, $team)->withPermissions('view')->invite();

    $connection = Connections::between($requester, $team)->withPermissions('view')->connect();

    expect($connection->status)->toBe(ConnectionStatus::Pending)
        ->and($requester->isConnectedTo($team))->toBeFalse();
});

test('re-connecting under default_status=pending does not demote an accepted connection', function (): void {
    config()->set('connections.default_status', 'pending');
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->invite();
    $team->acceptConnectionFrom($user);

    $connection = Connections::between($user, $team)->withPermissions('view', 'edit')->connect();

    expect($connection->status)->toBe(ConnectionStatus::Accepted)
        ->and($connection->permissions->all())->toBe(['view', 'edit']);
});

test('invite() on an accepted connection is an invalid transition', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    expect(fn () => Connections::between($user, $team)->invite())
        ->toThrow(InvalidStatusTransition::class, 'accepted');

    expect(Connections::between($user, $team)->find()?->status)->toBe(ConnectionStatus::Accepted);
});

test('re-inviting a pending connection stays pending', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    expect(Connections::between($user, $team)->withPermissions('view')->invite()->status)
        ->toBe(ConnectionStatus::Pending);
});

test('the raw action may explicitly accept a pending connection', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    $connection = app(CreateConnection::class)->execute($user, $team, status: ConnectionStatus::Accepted);

    expect($connection->status)->toBe(ConnectionStatus::Accepted)
        ->and($connection->fresh()?->status)->toBe(ConnectionStatus::Accepted);
});

test('the raw action may explicitly block any connection', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    $connection = app(CreateConnection::class)->execute($user, $team, status: ConnectionStatus::Blocked);

    expect($connection->fresh()?->status)->toBe(ConnectionStatus::Blocked);
});

test('a row that appears between the lookup and the insert is updated, never duplicated', function (): void {
    [$spammer, $team] = statusGuardBlockedPair();

    // Hide the pair from the first lookup only: the insert then hits the unique index exactly
    // as it would against a concurrent writer's committed row, and the write must land on
    // that row under the same status rules instead of crashing or duplicating it.
    $hidden = false;
    Connection::addGlobalScope('hide-once', function (Builder $query) use (&$hidden): void {
        if (! $hidden) {
            $hidden = true;
            $query->whereRaw('1 = 0');
        }
    });

    try {
        $connection = $spammer->connectTo($team, ['post']);
    } finally {
        Connection::clearBootedModels();
    }

    expect($hidden)->toBeTrue()
        ->and($connection->status)->toBe(ConnectionStatus::Blocked)
        ->and(Connection::query()->withTrashed()->count())->toBe(1);
});

test('a sync target keeps a pending overlap pending', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    Connections::from($user)->sync([new SyncTarget($team, permissions: ['view'])]);

    expect(Connections::between($user, $team)->find()?->status)->toBe(ConnectionStatus::Pending);
});
