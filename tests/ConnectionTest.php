<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Models\Connection;

test('it returns prunable query', function () {
    Carbon::setTestNow('2023-09-08 09:30:00');

    $prunableQuery = (new Connection)->prunable()->toRawSql();

    expect($prunableQuery)
        ->toBe(
            'select * from "connections" where "expires_at" <= \'2023-09-08 09:30:00\' and "connections"."deleted_at" is null'
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

test('the legacy Connection class remains an alias of the Models class', function () {
    expect(is_a(RoundlyConsulting\Connections\Connection::class, Connection::class, true))->toBeTrue();
});
