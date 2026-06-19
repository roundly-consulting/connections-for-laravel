<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
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

test('it honours per-target permission attributes via an array map', function (): void {
    $user = User::create();
    $teamA = Team::create();

    $user->syncConnections([
        ['model' => $teamA, 'permissions' => ['view', 'edit']],
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

test('a target array without a model throws', function (): void {
    $user = User::create();

    $user->syncConnections([['permissions' => ['view']]]);
})->throws(InvalidArgumentException::class);

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
