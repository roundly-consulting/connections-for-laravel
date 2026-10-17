<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Fixtures\CountedConnection;

/**
 * The shipped factory builds the model the host configured. Without a `modelName()` override
 * it built its packaged `$model`, so a host seeding through `Connection::factory()` got packaged
 * rows — without its own casts, events or observers. Exact classes, not `instanceof`: the
 * custom model extends the packaged one, so `toBeInstanceOf` passes either way.
 */
it('builds the configured connection model', function (): void {
    expect(Connection::factory()->create()::class)->toBe(CountedConnection::class)
        ->and(Connection::factory()->pending()->create()::class)->toBe(CountedConnection::class)
        ->and(CountedConnection::factory()->create()::class)->toBe(CountedConnection::class);
});

it('fires the host model events for a factory-built connection', function (): void {
    CountedConnection::resetCreationCount();

    Connection::factory()->count(2)->create();

    expect(CountedConnection::creationCount())->toBe(2);
});
