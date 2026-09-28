<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionInvited;
use RoundlyConsulting\Connections\Events\ConnectionRestored;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, HIGH ×2):
 *  - a soft-deleted row still holds the pair's unique morph index, so connect / grant / invite /
 *    toggle / sync after a disconnect or prune threw UniqueConstraintViolationException;
 *  - re-connecting without restating permissions / expiry wiped them.
 */

function revivalDisconnectedPair(): array
{
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->withMeta(['old' => true])->connect();
    Connections::between($user, $team)->disconnect();

    return [$user, $team];
}

test('connect() after a disconnect revives the pair as a fresh connection', function (): void {
    [$user, $team] = revivalDisconnectedPair();

    Event::fake();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->trashed())->toBeFalse()
        ->and($connection->status)->toBe(ConnectionStatus::Accepted)
        ->and($connection->permissions->all())->toBe([])
        ->and($connection->meta)->toBeNull()
        ->and(Connection::query()->withTrashed()->count())->toBe(1)
        ->and($user->isConnectedTo($team))->toBeTrue();

    Event::assertDispatched(ConnectionCreated::class);
    Event::assertNotDispatched(ConnectionUpdated::class);
    Event::assertNotDispatched(ConnectionRestored::class);
});

test('grant() after a disconnect revives the pair with the granted permissions', function (): void {
    [$user, $team] = revivalDisconnectedPair();

    $connection = Connections::between($user, $team)->permissions()->grant('edit');

    expect($connection->trashed())->toBeFalse()
        ->and($connection->permissions->all())->toBe(['edit'])
        ->and(Connection::query()->withTrashed()->count())->toBe(1);
});

test('permissions()->sync() after a disconnect revives the pair', function (): void {
    [$user, $team] = revivalDisconnectedPair();

    $connection = Connections::between($user, $team)->permissions()->sync('edit');

    expect($connection->trashed())->toBeFalse()
        ->and($connection->permissions->all())->toBe(['edit']);
});

test('invite() after a disconnect revives the pair as a pending invitation', function (): void {
    [$user, $team] = revivalDisconnectedPair();

    Event::fake();

    $connection = Connections::between($user, $team)->invite();

    expect($connection->trashed())->toBeFalse()
        ->and($connection->status)->toBe(ConnectionStatus::Pending);

    Event::assertDispatched(ConnectionInvited::class);
});

test('toggle() can run on and off repeatedly', function (): void {
    $user = User::create();
    $team = Team::create();

    expect($user->toggleConnection($team))->toBeInstanceOf(Connection::class)
        ->and($user->toggleConnection($team))->toBeNull()
        ->and($user->toggleConnection($team))->toBeInstanceOf(Connection::class)
        ->and($user->toggleConnection($team))->toBeNull()
        ->and($user->toggleConnection($team))->toBeInstanceOf(Connection::class)
        ->and(Connection::query()->withTrashed()->count())->toBe(1)
        ->and($user->isConnectedTo($team))->toBeTrue();
});

test('connectTo() after connections:prune revives the pair', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->expiresIn(now()->subMinute())->connect();
    Connections::prune();

    $connection = $user->connectTo($team);

    expect($connection->trashed())->toBeFalse()
        ->and($connection->expires_at)->toBeNull()
        ->and($user->isConnectedTo($team))->toBeTrue();
});

test('sync() re-adding a detached target revives it and reports it attached', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->sync([$teamA, $teamB]);
    Connections::from($user)->sync([$teamA]);

    $result = Connections::from($user)->sync([$teamA, new SyncTarget($teamB, permissions: ['read'])]);

    expect($result->attached)->toBe([$teamB->id])
        ->and($result->updated)->toBe([$teamA->id])
        ->and(Connections::between($user, $teamB)->find()?->permissions->all())->toBe(['read']);
});

test('a revived row re-applies the configured defaults', function (): void {
    [$user, $team] = revivalDisconnectedPair();
    config()->set('connections.default_permissions', ['view']);
    config()->set('connections.expiry.default', '30 days');

    $connection = Connections::between($user, $team)->connect();

    expect($connection->permissions->all())->toBe(['view'])
        ->and($connection->expires_at?->isFuture())->toBeTrue();
});

test('a re-connect that only merges meta keeps permissions and expiry', function (): void {
    $user = User::create();
    $team = Team::create();
    $expiry = Carbon::now()->addMonth()->startOfSecond();
    Connections::between($user, $team)
        ->withPermissions('view', 'edit')
        ->expiringAt($expiry)
        ->withMeta(['role' => 'owner'])
        ->connect();

    $connection = Connections::between($user, $team)->withMeta(['note' => 'hi'])->connect();

    expect($connection->permissions->all())->toBe(['view', 'edit'])
        ->and($connection->expires_at?->equalTo($expiry))->toBeTrue()
        ->and($connection->meta)->toBe(['role' => 'owner', 'note' => 'hi'])
        ->and($connection->fresh()?->permissions->all())->toBe(['view', 'edit']);
});

test('a re-connect replaces permissions and expiry only when they are supplied', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->expiresIn(3600)->connect();

    $expiry = Carbon::now()->addYear()->startOfSecond();
    $connection = Connections::between($user, $team)->withPermissions('admin')->expiringAt($expiry)->connect();

    expect($connection->permissions->all())->toBe(['admin'])
        ->and($connection->expires_at?->equalTo($expiry))->toBeTrue();
});

test('a re-connect does not re-apply the configured defaults', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('admin')->connect();
    config()->set('connections.default_permissions', ['view']);
    config()->set('connections.expiry.default', '30 days');

    $connection = Connections::between($user, $team)->connect();

    expect($connection->permissions->all())->toBe(['admin'])
        ->and($connection->expires_at)->toBeNull();
});

test('a sync overlap keeps permissions the target does not restate', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::between($user, $teamA)->withPermissions('admin')->connect();

    Connections::from($user)->sync([$teamA, $teamB]);

    expect(Connections::between($user, $teamA)->find()?->permissions->all())->toBe(['admin']);
});

test('toggle() on an expired connection does not make it permanent', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->expiresIn(now()->subMinute())->connect();

    $connection = $user->toggleConnection($team);

    expect($connection?->expires_at)->not->toBeNull()
        ->and($connection?->isExpired())->toBeTrue()
        ->and($user->isConnectedTo($team))->toBeFalse();
});

test('reconnect() of a live pair keeps its attributes', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('admin')->expiresIn(3600)->connect();

    $connection = Connections::between($user, $team)->reconnect();

    expect($connection->permissions->all())->toBe(['admin'])
        ->and($connection->expires_at)->not->toBeNull();
});
