<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Connections\Events\ConnectionAccepted;
use RoundlyConsulting\Connections\Events\ConnectionBlocked;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionInvited;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
use RoundlyConsulting\Connections\Events\ConnectionRestored;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-10-05 chat review, C-4): events fired inside a still-open outer
 * transaction, so listeners heard about rows that were then rolled back. These use real
 * listeners — `Event::fake()` would swap out the dispatcher the fix lives in.
 *
 * @param  list<class-string>  $events
 * @return ArrayObject<int, string>
 */
function listenTo(array $events): ArrayObject
{
    $heard = new ArrayObject;

    foreach ($events as $event) {
        Event::listen($event, function (object $fired) use ($heard): void {
            $heard[] = class_basename($fired).'#'.$fired->connection->connectable_id;
        });
    }

    return $heard;
}

test('it dispatches ConnectionCreated on first connect', function (): void {
    Event::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::assertDispatched(ConnectionCreated::class);
});

test('it dispatches ConnectionUpdated when re-connecting', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::fake();

    Connections::between($user, $team)->withPermissions('view')->connect();

    Event::assertDispatched(ConnectionUpdated::class);
});

test('it dispatches ConnectionRemoved on disconnect', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::fake();

    Connections::between($user, $team)->disconnect();

    Event::assertDispatched(ConnectionRemoved::class);
});

test('it dispatches ConnectionPermissionsChanged on grant', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::fake();

    Connections::between($user, $team)->permissions()->grant('view');

    Event::assertDispatched(ConnectionPermissionsChanged::class, function (ConnectionPermissionsChanged $event): bool {
        return $event->previous === [] && $event->current === ['view'];
    });
});

test('it does not dispatch a permission event when nothing changed', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    Event::fake();

    Connections::between($user, $team)->permissions()->grant('view');

    Event::assertNotDispatched(ConnectionPermissionsChanged::class);
});

test('events can be disabled via config', function (): void {
    config()->set('connections.events.enabled', false);

    Event::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    Event::assertNotDispatched(ConnectionCreated::class);
});

test('regression: a rolled-back connectAll dispatches nothing for the rolled-back target', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::between($user, $teamB)->connect();

    $heard = listenTo([ConnectionCreated::class, ConnectionInvited::class, ConnectionUpdated::class]);

    expect(fn () => Connections::from($user)->toMany([$teamA, $teamB])->asPending()->connectAll())
        ->toThrow(InvalidStatusTransition::class);

    expect($heard->getArrayCopy())->toBe([])
        ->and(Connections::between($user, $teamA)->find())->toBeNull();
});

/**
 * Throw from inside the query log on the nth statement that matches — after the earlier
 * ones were written, which is the point: the failure lands mid-batch.
 */
function failOnNth(int $nth, string $prefix): void
{
    $seen = 0;

    DB::listen(function (QueryExecuted $query) use (&$seen, $nth, $prefix): void {
        $sql = strtolower($query->sql);

        if (str_starts_with($sql, $prefix) && str_contains($sql, 'connections') && ++$seen === $nth) {
            throw new RuntimeException('boom');
        }
    });
}

test('regression: a rolled-back sync dispatches no created event', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    failOnNth(2, 'insert into');

    $heard = listenTo([ConnectionCreated::class]);

    expect(fn () => $user->syncConnections([$teamA, $teamB]))->toThrow(RuntimeException::class, 'boom');

    expect($heard->getArrayCopy())->toBe([])
        ->and(Connections::between($user, $teamA)->find())->toBeNull();
});

test('regression: a rolled-back sync dispatches no removed event', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    $user->connectTo($teamA);
    $user->connectTo($teamB);

    // The second detach's soft delete fails after the first one was written.
    failOnNth(2, 'update');

    $heard = listenTo([ConnectionRemoved::class]);

    expect(fn () => $user->syncConnections([]))->toThrow(RuntimeException::class, 'boom');

    expect($heard->getArrayCopy())->toBe([])
        ->and($user->isConnectedTo($teamA))->toBeTrue()
        ->and($user->isConnectedTo($teamB))->toBeTrue();
});

test('regression: events wait for a host transaction to commit and are dropped on its rollback', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    $heard = listenTo([ConnectionCreated::class]);

    DB::transaction(function () use ($user, $teamA, $heard): void {
        Connections::between($user, $teamA)->connect();

        expect($heard->getArrayCopy())->toBe([]);
    });

    expect($heard->getArrayCopy())->toBe(['ConnectionCreated#'.$teamA->getKey()]);

    try {
        DB::transaction(function () use ($user, $teamB): void {
            Connections::between($user, $teamB)->connect();

            throw new RuntimeException('host rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($heard->getArrayCopy())->toBe(['ConnectionCreated#'.$teamA->getKey()]);
});

test('every write event is dispatched after commit', function (string $event): void {
    expect(is_subclass_of($event, ShouldDispatchAfterCommit::class))->toBeTrue();
})->with([
    ConnectionAccepted::class,
    ConnectionBlocked::class,
    ConnectionCreated::class,
    ConnectionInvited::class,
    ConnectionPermissionsChanged::class,
    ConnectionRemoved::class,
    ConnectionRestored::class,
    ConnectionUpdated::class,
]);

test('a committed connect still reaches a real listener', function (): void {
    $user = User::create();
    $team = Team::create();

    $heard = listenTo([ConnectionCreated::class, ConnectionInvited::class]);

    Connections::between($user, $team)->invite();

    expect($heard->getArrayCopy())->toBe([
        'ConnectionCreated#'.$team->getKey(),
        'ConnectionInvited#'.$team->getKey(),
    ]);
});
