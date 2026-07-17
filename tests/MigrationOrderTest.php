<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Connections ships exactly one CREATE and zero foreign keys — both owners are
 * polymorphic `morphs()`, deliberately unconstrained because a host's connector and
 * connectable can live in any table.
 *
 * That shape decides what is worth pinning, and it is worth being explicit:
 *
 *  - **M (`toHaveRunnableMigrationOrder`) is not adopted.** `assertRunnable()` checks two
 *    independent things — FK ordering and that a `Schema::table()` ALTER sorts after its
 *    CREATE. Connections has neither: one file, no FK edges, no ALTER. (Approvals is the
 *    counter-example in this same batch: 0 FK but 2 ALTERs, so M *is* adopted there.)
 *  - **The R negative control (`toRejectBrokenOrderOnConnection`) is not adoptable.** It
 *    asserts the engine *refuses* a reordered set — but reversing a one-file list is the
 *    same list, and with no foreign keys Postgres has nothing to refuse. It fails loudly
 *    by design: the assertion working correctly against a shape it does not fit.
 */
$migrations = __DIR__.'/../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5,
 * on three packages). `count: 1` pins the file count so neither check can pass over an
 * empty or relocated directory.
 */
it('never auto-loads its migration — the host publishes it', function (): void {
    expect(ConnectionsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migration timestamp-injected into the host', function (): void {
    expect(ConnectionsServiceProvider::class)->toPublishMigrationsTimestamped('connections-migrations', 1);
});

/**
 * R — the real-engine proof. Connections' DDL had never met a real engine before this
 * row. `migrations: 1` pins the count, and the expectation additionally fails a set that
 * "applies cleanly" while creating no tables — an empty `up()` otherwise passes and
 * proves nothing.
 */
it('applies its migration on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the driver the leg *declares* (TESTING_DB_DRIVER)
 * against what the connection itself *answers*, so a "pgsql" job that quietly ran on
 * SQLite — the exact failure the leg exists to prevent — is impossible rather than merely
 * detectable by reading a skip count.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The jsonb columns, round-tripped on whatever engine the leg configured.
 *
 * This is the pin that matters most for this schema. `permissions` and `meta` were `json`
 * until this row. On Postgres `json` is stored as text and has **no equality operator**,
 * so `where permissions = ?` fails outright — measured, not theorised: this suite's
 * `assertDatabaseHas()` calls died with `operator does not exist: json = unknown` on the
 * pgsql leg, while staying green forever on SQLite, which has no such distinction.
 *
 * So this asserts the two things the switch bought: the values survive a round trip
 * (nothing depended on json's exact text — both columns cast through collection/array),
 * and equality against the column actually works on the engine.
 */
it('round-trips the jsonb columns on the configured engine', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, ['view', 'edit'], meta: ['tier' => 2, 'note' => 'launch']);

    $fresh = $connection->fresh();

    expect($fresh->permissions->all())->toBe(['view', 'edit'])
        // `toEqual`, not `toBe`, and the distinction is the whole point of the next test:
        // jsonb stores a parsed object, so it does not preserve OBJECT KEY ORDER. `toBe`
        // is `===`, which for arrays requires identical order, and this went red on the
        // pgsql leg ('tier','note' came back 'note','tier') while passing on SQLite.
        // Nothing in the package depends on that order — meta is read with data_get() and
        // merged by spread — so the fix is to assert content, not order.
        ->and($fresh->meta)->toEqual(['tier' => 2, 'note' => 'launch'])
        ->and($fresh->meta('tier'))->toBe(2)
        // Containment through the scope — a jsonb @> on Postgres, and the query the
        // permission scopes actually run.
        ->and($user->connections()->withPermission('view')->count())->toBe(1)
        ->and($user->connections()->withPermission('delete')->count())->toBe(0)
        // The driver actually under test, so a leg that quietly stayed on sqlite is
        // visible in the failure rather than passing as a "postgres" run.
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});

/**
 * The column really is jsonb on Postgres, not merely usable. Without this the switch
 * could be reverted and the round-trip above would still pass on the engine's text type,
 * losing indexability and equality silently.
 */
it('declares the permission columns as jsonb on postgres', function (): void {
    $type = DB::selectOne(
        "select data_type from information_schema.columns where table_name = 'connections' and column_name = 'permissions'",
    );

    expect($type?->data_type)->toBe('jsonb');
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql', 'pgsql-only column type');

/**
 * jsonb's one real behaviour change, pinned rather than left to surprise someone.
 *
 * jsonb stores a parsed object and does NOT preserve object key order (it orders keys by
 * length, then bytewise). A list keeps its element order — only object keys move. This is
 * the single observable difference the json -> jsonb switch introduced, and it is pinned
 * here so it is a documented property with a test attached rather than a latent trap:
 * a host that compares a *refreshed* meta bag with `===`, or json_encodes it for an ETag
 * or a signature, will see a different order than it wrote.
 *
 * Nothing in this package depends on it (meta is read through data_get() and merged by
 * spread; permissions is a list), which is why the switch is safe here.
 */
it('preserves list order but normalises object key order on jsonb', function (): void {
    $user = User::create();
    $team = Team::create();

    // 'tier' (4) sorts before 'invited_by' (10) under jsonb's length-first ordering, so a
    // round trip reorders them — while the permissions list stays exactly as written.
    $connection = $user->connectTo($team, ['zebra', 'alpha'], meta: ['invited_by' => 'admin', 'tier' => 2]);

    $fresh = $connection->fresh();

    // Lists keep their order on every driver: a permission list is not a set.
    expect($fresh->permissions->all())->toBe(['zebra', 'alpha'])
        // The content survives regardless of driver.
        ->and($fresh->meta)->toEqual(['invited_by' => 'admin', 'tier' => 2]);

    // The order itself is only normalised on a real jsonb engine.
    if (DriverMatrix::driver() === 'pgsql') {
        expect(array_keys((array) $fresh->meta))->toBe(['tier', 'invited_by']);
    }
});
