<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Tests\Fixtures\SecondaryConnection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it attaches missing, detaches extras and updates overlap', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    $teamC = Team::create();

    $user->connectTo($teamA);
    $user->connectTo($teamB);

    $result = $user->syncConnections([$teamA, $teamC]);

    expect($result)->toBeInstanceOf(SyncResult::class)
        ->and($result->attached)->toBe([$teamC->getKey()])
        ->and($result->detached)->toBe([$teamB->getKey()])
        ->and($result->updated)->toBe([$teamA->getKey()])
        ->and($user->fresh()->isConnectedTo($teamA))->toBeTrue()
        ->and($user->fresh()->isConnectedTo($teamB))->toBeFalse()
        ->and($user->fresh()->isConnectedTo($teamC))->toBeTrue();
});

test('it fires a removed event for each detached connection', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    $user->connectTo($teamA);
    $user->connectTo($teamB);

    Event::fake();

    $user->syncConnections([$teamA]);

    Event::assertDispatched(ConnectionRemoved::class, 1);
});

test('it honours per-target permissions through a SyncTarget', function (): void {
    $user = User::create();
    $teamA = Team::create();

    $user->syncConnections([
        new SyncTarget($teamA, permissions: ['view', 'edit']),
    ]);

    expect($user->hasPermissionThroughConnection($teamA, 'view'))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($teamA, 'edit'))->toBeTrue();
});

test('it accepts SyncTarget DTOs directly', function (): void {
    $user = User::create();
    $teamA = Team::create();

    $user->syncConnections([
        new SyncTarget($teamA, ['publish'], now()->addDays(5)),
    ]);

    expect($user->hasPermissionThroughConnection($teamA, 'publish'))->toBeTrue();
});

test('an empty set detaches everything', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $user->connectTo($teamA);

    $result = $user->syncConnections([]);

    expect($result->detached)->toBe([$teamA->getKey()])
        ->and($user->fresh()->isConnectedTo($teamA))->toBeFalse();
});

test('a shape-array target is rejected (pass a SyncTarget instead)', function (): void {
    $user = User::create();

    $user->syncConnections([['permissions' => ['view']]]);
})->throws(TypeError::class);

test('a failure mid-sync rolls back the whole reconcile', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    // teamB has no real row, but we delete it after building the model so the
    // foreign-key-free insert still works; instead force a failure by passing a
    // connectable whose key triggers a query error.
    $failing = new class extends Team
    {
        public function getKey(): mixed
        {
            throw new RuntimeException('boom');
        }
    };

    try {
        $user->syncConnections([
            new SyncTarget($teamA),
            new SyncTarget($failing),
        ]);
    } catch (Throwable) {
        // expected
    }

    expect($user->fresh()->isConnectedTo($teamA))->toBeFalse();
});

test('regression: a duplicated target is attached once, not also reported as updated', function (): void {
    Event::fake([ConnectionCreated::class, ConnectionUpdated::class]);

    $user = User::create();
    $team = Team::create();

    $result = $user->syncConnections([$team, $team]);

    expect($result->attached)->toBe([$team->getKey()])
        ->and($result->updated)->toBe([])
        ->and($result->detached)->toBe([]);

    Event::assertDispatchedTimes(ConnectionCreated::class, 1);
    Event::assertNotDispatched(ConnectionUpdated::class);
});

test('regression: of two targets for one model, the last one wins', function (): void {
    $user = User::create();
    $team = Team::create();

    $result = $user->syncConnections([
        new SyncTarget($team, permissions: ['view']),
        new SyncTarget($team, permissions: ['edit']),
    ]);

    expect($result->attached)->toBe([$team->getKey()])
        ->and($result->updated)->toBe([])
        ->and($user->permissionsThroughConnection($team)->all())->toBe(['edit']);
});

test('regression: a failure mid-sync rolls back on the connection model\'s own database', function (): void {
    SecondaryConnection::install();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    $teamC = Team::create();
    $user->connectTo($teamC);

    // Fails on the second target's insert, after teamA's row was written.
    SecondaryConnection::creating(function (SecondaryConnection $connection) use ($teamB): void {
        if ($connection->connectable_id === $teamB->getKey()) {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => $user->syncConnections([$teamA, $teamB]))->toThrow(RuntimeException::class, 'boom');

    expect(SecondaryConnection::query()->withTrashed()->where('connectable_id', $teamA->getKey())->exists())->toBeFalse()
        ->and($user->isConnectedTo($teamC))->toBeTrue();
});
