<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\ConnectionPermissions;
use RoundlyConsulting\Connections\Exceptions\ConnectionsException;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\PendingConnection;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The presets replace this file's generic rules; the package's domain-specific ones are
 * kept below. Note the file had no `declare(strict_types=1)` of its own and no strict-types
 * rule at all — the first preset now covers the source it was missing.
 */
ArchPresets::strictTypes('RoundlyConsulting\Connections');

/**
 * Replaces three hand-written finality rules scoped to Actions, Events and
 * DataTransferObjects. Those covered three namespaces and left the rest of the package
 * unguarded; the preset covers everything and names its exemptions instead. That widening
 * is not theoretical — it immediately surfaced PendingConnection, a class none of the
 * three scoped rules reached.
 *
 * The five exemptions are deliberate extension points, each verified to be extended by
 * shipped code rather than assumed:
 *  - Connection, which `connections.model` invites a host to subclass (pinned by the
 *    preset below instead);
 *  - ConnectionsException, the base every connections error extends so a host can catch
 *    them uniformly;
 *  - PendingConnection, extended by the shipped RecordingPendingConnection;
 *  - ConnectionPermissions, extended by the shipped RecordingConnectionPermissions;
 *  - ConnectionManager, extended by the shipped ConnectionFake.
 *
 * `final` on any of them is a fatal error, not a tightening. Both of the last two were
 * invisible to the rules this replaces — which is the argument for the wider scope.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Connections', [
    Connection::class,
    ConnectionsException::class,
    PendingConnection::class,
    ConnectionPermissions::class,
    ConnectionManager::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. The preset
 * also pins that `connections.model` really defaults to the packaged model, so the seam
 * cannot rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Connection::class => 'connections.model',
]);

/**
 * Connections does no cryptography; the ban is a standing guard against an invitation
 * token or signature scheme being hand-rolled here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Connections');

/**
 * `connections.model` resolves through the ConnectionModel seam in Support. Adopted
 * rather than rejected as jwt rejected it: connections has the shape the preset targets —
 * a real Eloquent model behind a `*_model`-style key, resolved through a Support seam.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support');

/**
 * The morph-key seam, guarded. Connections migrated its morph columns off raw
 * `$table->morphs()` onto `morphKey($name, KeyType::…)` so a uuid/ulid host can flip its
 * whole graph coherently — a hardcoded bigint id breaks those hosts on Postgres, and SQLite
 * type affinity hides it. This pin reds if a future migration reintroduces a raw morph and
 * bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: connections' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

/**
 * Replaces `it will not use debugging functions`, which covered dd/dump/ray. The preset
 * adds var_dump and print_r.
 */
ArchPresets::noDebuggingLeftovers();

// ---------------------------------------------------------------------------
// The package's own domain rules. No preset equivalent, so they are kept.
// ---------------------------------------------------------------------------

// `finalByDefault` above pins that DTOs are final; readonly is a separate property no
// preset expresses.
it('keeps data transfer objects readonly')
    ->expect('RoundlyConsulting\Connections\DataTransferObjects')
    ->classes()
    ->toBeReadonly();

it('uses enums for connection status')
    ->expect('RoundlyConsulting\Connections\Enums\ConnectionStatus')
    ->toBeEnum();
