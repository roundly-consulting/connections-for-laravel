<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The `connections.key_type` seam. A connection joins two polymorphic endpoints —
 * connector and connectable — and both id columns must follow the host's key type or a
 * uuid/ulid-keyed model can never be connected on a strict engine. The shipped bigint
 * schema stays byte-identical.
 */
if (! function_exists('morphKtColumn')) {
    /**
     * @return array{type: string, type_name: string, nullable: bool|null}
     */
    function morphKtColumn(string $table, string $column): array
    {
        foreach (Schema::getColumns($table) as $c) {
            if ($c['name'] === $column) {
                return ['type' => $c['type'], 'type_name' => $c['type_name'], 'nullable' => $c['nullable']];
            }
        }

        return ['type' => 'MISSING', 'type_name' => 'MISSING', 'nullable' => null];
    }
}

$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';
$table = fn (): string => (string) config('connections.table', 'connections');

it('emits byte-identical morph columns on the bigint default', function () use ($table): void {
    // Both connection endpoints are required (non-null) morphs.
    Schema::dropIfExists('kt_ref');
    Schema::create('kt_ref', function (Blueprint $t): void {
        $t->id();
        $t->morphs('req');
    });

    $req = morphKtColumn('kt_ref', 'req_id');
    $reqType = morphKtColumn('kt_ref', 'req_type');

    expect(morphKtColumn($table(), 'connector_id'))->toBe($req)
        ->and(morphKtColumn($table(), 'connector_type'))->toBe($reqType)
        ->and(morphKtColumn($table(), 'connectable_id'))->toBe($req)
        ->and(morphKtColumn($table(), 'connectable_type'))->toBe($reqType);

    Schema::dropIfExists('kt_ref');
});

it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected) use ($table): void {
    config()->set('connections.key_type', $keyType);

    Schema::dropIfExists($table());
    $migration = require __DIR__.'/../../database/migrations/create_connections_table.php';
    $migration->up();

    expect(morphKtColumn($table(), 'connector_id')['type'])->toBe($expected)
        ->and(morphKtColumn($table(), 'connectable_id')['type'])->toBe($expected)
        ->and(morphKtColumn($table(), 'connector_type')['type'])->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart — sqlite affinity hides it');

it('falls back to the bigint schema for an unrecognized key type', function () use ($table): void {
    config()->set('connections.key_type', 'nonsense');

    Schema::dropIfExists($table());
    $migration = require __DIR__.'/../../database/migrations/create_connections_table.php';
    $migration->up();

    $expected = DriverMatrix::driver() === 'pgsql' ? 'bigint' : 'integer';

    expect(morphKtColumn($table(), 'connector_id')['type'])->toBe($expected)
        ->and(morphKtColumn($table(), 'connectable_id')['type'])->toBe($expected);
});
