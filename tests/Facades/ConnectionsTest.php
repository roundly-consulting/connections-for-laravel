<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Actions\SyncConnections;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\ConnectionPermissions;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Exceptions\MissingConnectable;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('the facade resolves the connection manager', function (): void {
    expect(Connections::getFacadeRoot())->toBeInstanceOf(ConnectionManager::class);
});

test('an injected manager runs the same API as the facade', function (): void {
    $manager = app(ConnectionManager::class);
    $user = User::create();
    $team = Team::create();

    $manager->between($user, $team)->withPermissions('view')->connect();

    expect($manager)->toBe(Connections::getFacadeRoot())
        ->and($manager->between($user, $team)->permissions()->has('view'))->toBeTrue()
        ->and($manager->between($user, $team)->find())->toBeInstanceOf(Connection::class);
});

test('from()->sync() reconciles the connector to exactly the given set', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    $teamC = Team::create();
    Connections::between($user, $teamA)->connect();
    Connections::between($user, $teamB)->connect();

    $result = Connections::from($user)->sync([$teamA, new SyncTarget($teamC, permissions: ['read'])]);

    expect($result)->toBeInstanceOf(SyncResult::class)
        ->and($result->attached)->toBe([$teamC->getKey()])
        ->and($result->detached)->toBe([$teamB->getKey()])
        ->and($result->updated)->toBe([$teamA->getKey()])
        ->and(Connections::between($user, $teamC)->permissions()->all()->all())->toBe(['read'])
        ->and(Connections::between($user, $teamB)->exists())->toBeFalse();
});

test('from()->sync() accepts any iterable', function (): void {
    $user = User::create();
    $team = Team::create();

    $result = Connections::from($user)->sync(collect([$team]));

    expect($result->attached)->toBe([$team->getKey()]);
});

test('the raw SyncConnections action is the same use case', function (): void {
    $user = User::create();
    $team = Team::create();

    $result = app(SyncConnections::class)->execute($user, [new SyncTarget($team)]);

    expect($result->attached)->toBe([$team->getKey()])
        ->and(Connections::between($user, $team)->exists())->toBeTrue();
});

test('between()->find() returns the pair connection in any status', function (): void {
    $user = User::create();
    $team = Team::create();

    expect(Connections::between($user, $team)->find())->toBeNull();

    Connections::between($user, $team)->invite();

    $found = Connections::between($user, $team)->find();

    expect($found)->toBeInstanceOf(Connection::class)
        ->and($found?->status)->toBe(ConnectionStatus::Pending)
        ->and(Connections::between($user, $team)->exists())->toBeFalse();
});

test('between()->find() ignores the reverse direction and soft-deleted rows', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    expect(Connections::between($team, $user)->find())->toBeNull();

    Connections::between($user, $team)->disconnect();

    expect(Connections::between($user, $team)->find())->toBeNull();
});

test('find() and permissions() need a connectable', function (string $verb): void {
    $user = User::create();

    Connections::from($user)->{$verb}();
})->with(['find', 'permissions'])->throws(MissingConnectable::class);

test('permissions() is a sub-accessor over the pair', function (): void {
    $user = User::create();
    $team = Team::create();

    expect(Connections::between($user, $team)->permissions())->toBeInstanceOf(ConnectionPermissions::class);
});

test('permissions()->grant() creates the connection when absent', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->permissions()->grant('read', 'write');

    expect($connection->permissions->all())->toBe(['read', 'write'])
        ->and(Connections::between($user, $team)->exists())->toBeTrue();
});

test('permissions()->revoke() removes permissions and fails without a connection', function (): void {
    $user = User::create();
    $team = Team::create();
    $other = Team::create();
    Connections::between($user, $team)->withPermissions('read', 'write')->connect();

    expect(Connections::between($user, $team)->permissions()->revoke('write')->permissions->all())->toBe(['read']);

    Connections::between($user, $other)->permissions()->revoke('read');
})->throws(ConnectionNotFound::class);

test('permissions()->sync() replaces the set and clear() empties it', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('read')->connect();

    Connections::between($user, $team)->permissions()->sync('r', 'w');

    expect(Connections::between($user, $team)->permissions()->all()->all())->toBe(['r', 'w']);

    Connections::between($user, $team)->permissions()->clear();

    expect(Connections::between($user, $team)->permissions()->all()->all())->toBe([]);
});

test('permissions() reads all / has / hasAny / hasAll', function (): void {
    $user = User::create();
    $team = Team::create();
    $stranger = Team::create();
    Connections::between($user, $team)->withPermissions('read', 'posts.*')->connect();

    $permissions = Connections::between($user, $team)->permissions();

    expect($permissions->all()->all())->toBe(['read', 'posts.*'])
        ->and($permissions->has('read'))->toBeTrue()
        ->and($permissions->has('posts.edit'))->toBeTrue()
        ->and($permissions->has('write'))->toBeFalse()
        ->and($permissions->hasAny('write', 'read'))->toBeTrue()
        ->and($permissions->hasAny('write', 'delete'))->toBeFalse()
        ->and($permissions->hasAll('read', 'posts.edit'))->toBeTrue()
        ->and($permissions->hasAll('read', 'write'))->toBeFalse()
        ->and(Connections::between($user, $stranger)->permissions()->all()->all())->toBe([])
        ->and(Connections::between($user, $stranger)->permissions()->has('read'))->toBeFalse();
});

test('permissions() checks require an active connection by default', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('read')->invite();

    expect(Connections::between($user, $team)->permissions()->has('read'))->toBeFalse()
        ->and(Connections::between($user, $team)->permissions()->all()->all())->toBe(['read']);
});

test('expiring() returns every live connection expiring within the window', function (): void {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $soon = Connection::factory()->create(['expires_at' => now()->addDays(3)]);
    $later = Connection::factory()->create(['expires_at' => now()->addDays(20)]);
    Connection::factory()->create(['expires_at' => null]);
    Connection::factory()->expired()->create();

    $default = Connections::expiring();
    $wide = Connections::expiring(30);

    expect($default)->toBeInstanceOf(Builder::class)
        ->and($default->pluck('id')->all())->toBe([$soon->id])
        ->and($wide->orderBy('id')->pluck('id')->all())->toBe([$soon->id, $later->id]);

    Carbon::setTestNow();
});

test('flushCache() drops every cached connection', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('read')->connect();

    $user->hasPermissionThroughConnection($team, 'read');

    expect(Cache::has($user->connectionCacheKey($team)))->toBeTrue();

    Connections::flushCache();

    expect(Cache::has($user->connectionCacheKey($team)))->toBeFalse();
});

test('prune() is reachable through the facade', function (): void {
    Connection::factory()->expired()->create();

    expect(Connections::prune())->toBe(1);
});
