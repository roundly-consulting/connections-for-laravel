<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it returns prunable query', function () {
    Carbon::setTestNow('2023-09-08 09:30:00');

    $prunableQuery = (new Connection)->prunable()->toRawSql();

    // Identifiers quoted by the active grammar: `"` on sqlite/pgsql, backticks on mysql.
    $wrap = (new Connection)->getConnection()->getQueryGrammar()->wrap(...);

    expect($prunableQuery)
        ->toBe(
            "select * from {$wrap('connections')} where {$wrap('expires_at')} <= '2023-09-08 09:30:00' and {$wrap('status')} != 'blocked' and {$wrap('connections.deleted_at')} is null"
        );

    Carbon::setTestNow();
});

test('it prunes expired connections', function () {
    Connection::factory()->create(['expires_at' => now()->addDay()]);
    Connection::factory()->expired()->create();

    expect(Connection::query()->count())->toBe(2);

    $this->artisan('model:prune', ['--model' => [Connection::class]])->run();

    expect(Connection::query()->count())->toBe(1);
});

test('regression: model:prune keeps an expired block, so a later connect stays blocked', function () {
    $user = User::create();
    $blocked = Team::create();
    $accepted = Team::create();

    Connections::between($user, $blocked)->connect();
    Connections::between($user, $blocked)->block();
    Connections::between($user, $accepted)->connect();
    Connection::query()->update(['expires_at' => now()->subDay()]);

    $this->artisan('model:prune', ['--model' => [Connection::class]])->run();

    // The expired accepted row is still pruned; the expired block is not.
    expect(Connection::query()->withTrashed()->where('connectable_id', $accepted->getKey())->count())->toBe(0)
        ->and(Connection::query()->withTrashed()->where('connectable_id', $blocked->getKey())->count())->toBe(1);

    $connection = Connections::between($user, $blocked)->connect();

    expect($connection->status)->toBe(ConnectionStatus::Blocked)
        ->and($user->isConnectedTo($blocked))->toBeFalse();
});

test('it reports whether a permission is granted', function () {
    $connection = Connection::factory()->create([
        'permissions' => collect(['view', 'edit']),
    ]);

    expect($connection)
        ->hasPermission('view')->toBeTrue()
        ->hasPermission('delete')->toBeFalse();
});

test('it builds from its factory with sane defaults', function () {
    $connection = Connection::factory()->create();

    expect($connection)
        ->toBeInstanceOf(Connection::class)
        ->permissions->toBeEmpty()
        ->expires_at->toBeNull();
});
