<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;

test('it prunes expired connections and reports the count', function (): void {
    Connection::factory()->create(['expires_at' => now()->addDay()]);
    Connection::factory()->expired()->create();
    Connection::factory()->expired()->create();

    $this->artisan('connections:prune')
        ->expectsOutputToContain('Pruned 2 expired connection(s).')
        ->assertSuccessful();

    expect(Connection::query()->count())->toBe(1);
});

test('it reports zero when nothing is expired', function (): void {
    Connection::factory()->create(['expires_at' => now()->addDay()]);

    $this->artisan('connections:prune')
        ->expectsOutputToContain('Pruned 0 expired connection(s).')
        ->assertSuccessful();
});
