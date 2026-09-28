<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\PendingConnection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('the manager is bound as a singleton under its class name', function (): void {
    expect(app(ConnectionManager::class))
        ->toBeInstanceOf(ConnectionManager::class)
        ->toBe(app(ConnectionManager::class))
        ->and(Connections::getFacadeRoot())->toBe(app(ConnectionManager::class));
});

test('between and from return a pending connection', function (): void {
    $user = User::create();
    $team = Team::create();

    expect(app(ConnectionManager::class)->between($user, $team))->toBeInstanceOf(PendingConnection::class)
        ->and(app(ConnectionManager::class)->from($user))->toBeInstanceOf(PendingConnection::class);
});

test('prune removes expired connections and returns the count', function (): void {
    Connection::factory()->create(['expires_at' => now()->addDay()]);
    Connection::factory()->expired()->create();
    Connection::factory()->expired()->create();

    $pruned = Connections::prune();

    expect($pruned)->toBe(2)
        ->and(Connection::query()->count())->toBe(1);
});
