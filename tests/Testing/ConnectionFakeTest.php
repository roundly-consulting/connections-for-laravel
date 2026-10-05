<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Testing\ConnectionFake;
use RoundlyConsulting\Connections\Testing\RecordedOperation;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

use function Pest\Laravel\artisan;

/*
 * Every assert gets a passing and a failing case, driven through a HasConnections trait
 * call wherever the trait has the verb (the path that bypassed the fake before), and
 * through the facade builder where it does not.
 *
 * Each row: [setup before faking, the action, the positive assert, the assertNothing*].
 */
dataset('recorded verbs', [
    'connect (trait)' => [
        null,
        fn (User $user, Team $team) => $user->connectTo($team),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertConnected($user, $team),
        'assertNothingConnected',
    ],
    'invite (trait)' => [
        null,
        fn (User $user, Team $team) => $user->inviteConnection($team),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertInvited($user, $team),
        'assertNothingInvited',
    ],
    'accept (trait)' => [
        fn (User $user, Team $team) => $user->inviteConnection($team),
        fn (User $user, Team $team) => $team->acceptConnectionFrom($user),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertAccepted($user, $team),
        'assertNothingAccepted',
    ],
    'block (trait)' => [
        fn (User $user, Team $team) => $user->inviteConnection($team),
        fn (User $user, Team $team) => $team->blockConnectionFrom($user),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertBlocked($user, $team),
        'assertNothingBlocked',
    ],
    'disconnect (trait)' => [
        fn (User $user, Team $team) => $user->connectTo($team),
        fn (User $user, Team $team) => $user->disconnectFrom($team),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertDisconnected($user, $team),
        'assertNothingDisconnected',
    ],
    'reconnect (trait)' => [
        fn (User $user, Team $team) => $user->connectTo($team)->delete(),
        fn (User $user, Team $team) => $user->reconnectTo($team),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertReconnected($user, $team),
        'assertNothingReconnected',
    ],
    'restore (builder)' => [
        null,
        fn (User $user, Team $team) => Connections::between($user, $team)->restore(),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertReconnected($user, $team),
        'assertNothingReconnected',
    ],
    'extend (builder)' => [
        fn (User $user, Team $team) => $user->connectTo($team),
        fn (User $user, Team $team) => Connections::between($user, $team)->extend(now()->addWeek()),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertExtended($user, $team),
        'assertNothingExtended',
    ],
    'grant (trait)' => [
        null,
        fn (User $user, Team $team) => $user->grantThroughConnection($team, 'read'),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertGranted($user, $team, 'read'),
        'assertNothingGranted',
    ],
    'revoke (trait)' => [
        fn (User $user, Team $team) => $user->connectTo($team, ['read']),
        fn (User $user, Team $team) => $user->revokeThroughConnection($team, 'read'),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertRevoked($user, $team, 'read'),
        'assertNothingRevoked',
    ],
    'sync permissions (trait)' => [
        null,
        fn (User $user, Team $team) => $user->syncConnectionPermissions($team, ['w', 'r']),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertPermissionsSynced($user, $team, ['r', 'w']),
        'assertNothingPermissionsSynced',
    ],
    'clear permissions (trait)' => [
        fn (User $user, Team $team) => $user->connectTo($team, ['read']),
        fn (User $user, Team $team) => $user->clearConnectionPermissions($team),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertPermissionsCleared($user, $team),
        'assertNothingPermissionsSynced',
    ],
    'sync connections (trait)' => [
        null,
        fn (User $user, Team $team) => $user->syncConnections([$team]),
        fn (ConnectionFake $fake, User $user, Team $team) => $fake->assertSynced($user, [$team]),
        'assertNothingSynced',
    ],
    'prune (facade)' => [
        null,
        fn () => Connections::prune(),
        fn (ConnectionFake $fake) => $fake->assertPruned(),
        'assertNothingPruned',
    ],
]);

test('a recorded verb passes its assert and fails its assertNothing', function (?Closure $setup, Closure $act, Closure $assert, string $nothing): void {
    $user = User::create();
    $team = Team::create();
    $setup?->__invoke($user, $team);

    $fake = Connections::fake();
    $act($user, $team);

    $assert($fake, $user, $team);

    expect(fn () => $fake->{$nothing}())->toThrow(AssertionFailedError::class);
})->with('recorded verbs');

test('an unrecorded verb fails its assert and passes its assertNothing', function (?Closure $setup, Closure $act, Closure $assert, string $nothing): void {
    $user = User::create();
    $team = Team::create();
    $setup?->__invoke($user, $team);

    $fake = Connections::fake();

    $fake->{$nothing}();

    expect(fn () => $assert($fake, $user, $team))->toThrow(AssertionFailedError::class);
})->with('recorded verbs');

test('fake() swaps the facade root and the container binding', function (): void {
    $fake = Connections::fake();

    expect($fake)->toBeInstanceOf(ConnectionFake::class)
        ->toBeInstanceOf(ConnectionManager::class)
        ->and(Connections::getFacadeRoot())->toBe($fake)
        ->and(app(ConnectionManager::class))->toBe($fake);
});

test('a call through an injected manager is recorded', function (): void {
    $fake = Connections::fake();
    $user = User::create();
    $team = Team::create();

    app(ConnectionManager::class)->between($user, $team)->connect();

    $fake->assertConnected($user, $team);
});

test('the prune command goes through the fake', function (): void {
    $fake = Connections::fake();

    artisan('connections:prune')->assertSuccessful();

    $fake->assertPruned();
});

test('operations still hit the database (pass-through)', function (): void {
    Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    expect($user->isConnectedTo($team))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();
});

test('assertNotConnected passes for an unrecorded pair and fails for a recorded one', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $otherUser = User::create();
    $team = Team::create();
    $otherTeam = Team::create();

    Connections::between($otherUser, $team)->connect();

    $fake->assertNotConnected($user, $team);
    $fake->assertNotConnected($otherUser, $otherTeam);

    expect(fn () => $fake->assertNotConnected($otherUser, $team))->toThrow(AssertionFailedError::class);
});

test('assertConnectedTimes counts connect and invite operations', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    Connections::between($user, $teamA)->connect();
    $user->inviteConnection($teamB);

    $fake->assertConnectedTimes(2);

    expect(fn () => $fake->assertConnectedTimes(3))->toThrow(AssertionFailedError::class);
});

test('assertHasPermissionThrough matches any operation carrying the permission', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('publish')->connect();

    $fake->assertHasPermissionThrough($user, $team, 'publish');

    expect(fn () => $fake->assertHasPermissionThrough($user, $team, 'delete'))->toThrow(AssertionFailedError::class);
});

test('permission asserts can filter on the permission or the exact set', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->permissions()->grant('read');
    Connections::between($user, $team)->permissions()->revoke('read');
    Connections::between($user, $team)->permissions()->sync('a', 'b');

    $fake->assertGranted($user, $team);
    $fake->assertRevoked($user, $team);
    $fake->assertPermissionsSynced($user, $team);

    expect(fn () => $fake->assertGranted($user, $team, 'write'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertRevoked($user, $team, 'write'))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertPermissionsSynced($user, $team, ['a']))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertPermissionsCleared($user, $team))->toThrow(AssertionFailedError::class);
});

test('assertSynced can check the exact desired set', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    Connections::from($user)->sync([$teamA, new SyncTarget($teamB, permissions: ['read'])]);

    $fake->assertSynced($user);
    $fake->assertSynced($user, [$teamB, $teamA]);

    expect(fn () => $fake->assertSynced($user, [$teamA]))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertSynced($teamA))->toThrow(AssertionFailedError::class);
});

test('toggle records the connect and then the disconnect', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    $user->toggleConnection($team, ['read']);
    $user->toggleConnection($team);

    $fake->assertConnected($user, $team);
    $fake->assertHasPermissionThrough($user, $team, 'read');
    $fake->assertDisconnected($user, $team);
});

test('bulk verbs record one operation per staged target', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    Connections::from($user)->toMany([$teamA, $teamB])->withPermissions('view')->connectAll();
    Connections::from($user)->toMany([$teamA, $teamB])->grantAll('publish');
    Connections::from($user)->toMany([$teamA, $teamB])->withPermissions('edit')->grantAll();
    Connections::from($user)->toMany([$teamA, $teamB])->revokeAll('publish');
    Connections::from($user)->toMany([$teamA, $teamB])->disconnectAll();

    $fake->assertConnectedTimes(2);

    foreach ([$teamA, $teamB] as $team) {
        $fake->assertConnected($user, $team);
        $fake->assertGranted($user, $team, 'publish');
        $fake->assertGranted($user, $team, 'edit');
        $fake->assertRevoked($user, $team, 'publish');
        $fake->assertDisconnected($user, $team);
    }
});

test('a verb that throws records nothing', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    expect(fn () => $user->disconnectFrom($team))->toThrow(ConnectionNotFound::class);

    $fake->assertNothingDisconnected();
    $fake->assertNothingRecorded();
});

test('assertNothingRecorded fails once anything was recorded', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    $fake->assertNothingRecorded();

    $user->connectTo($team);

    expect(fn () => $fake->assertNothingRecorded())->toThrow(AssertionFailedError::class, 'recorded [connect]');
});

test('recorded() exposes the captured operations', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();
    Connections::between($user, $team)->permissions()->grant('view');
    Connections::between($user, $team)->permissions()->revoke('view');
    Connections::between($user, $team)->disconnect();

    expect($fake->recorded())->toHaveCount(4)
        ->each->toBeInstanceOf(RecordedOperation::class)
        ->and(array_map(static fn (RecordedOperation $operation): string => $operation->verb, $fake->recorded()))
        ->toBe(['connect', 'grant', 'revoke', 'disconnect']);
});

test('from() defers the connectable and still records', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::from($user)->to($team)->connect();

    $fake->assertConnected($user, $team);
});

test('regression: a HasConnections trait call is recorded by the fake', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    $user->connectTo($team);

    $fake->assertConnected($user, $team);
});

test('the fake returns the real connection models', function (): void {
    Connections::fake();

    $user = User::create();
    $team = Team::create();

    expect($user->connectTo($team))->toBeInstanceOf(Connection::class);
});

test('regression: asPending()->connect() and asPending()->connectAll() are recorded as invitations', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    Connections::between($user, $teamA)->asPending()->connect();
    Connections::from($user)->toMany([$teamB])->asPending()->connectAll();

    $fake->assertInvited($user, $teamA);
    $fake->assertInvited($user, $teamB);
    $fake->assertConnected($user, $teamA);
    $fake->assertConnected($user, $teamB);
    $fake->assertConnectedTimes(2);

    expect(fn () => $fake->assertNothingInvited())->toThrow(AssertionFailedError::class);
});

test('regression: a plain connect that creates an invitation under default_status=pending is recorded as one', function (): void {
    config()->set('connections.default_status', 'pending');

    $fake = Connections::fake();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    $connection = Connections::between($user, $teamA)->connect();
    Connections::from($user)->toMany([$teamB])->connectAll();

    expect($connection->isPending())->toBeTrue();

    $fake->assertInvited($user, $teamA);
    $fake->assertInvited($user, $teamB);
    $fake->assertConnectedTimes(2);
});

test('a connect that only updates an existing connection is not recorded as an invitation', function (): void {
    config()->set('connections.default_status', 'pending');

    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    $fake = Connections::fake();

    Connections::between($user, $team)->withPermissions('view')->connect();
    Connections::from($user)->toMany([$team])->withPermissions('edit')->connectAll();

    $fake->assertConnected($user, $team);
    $fake->assertNothingInvited();
});
