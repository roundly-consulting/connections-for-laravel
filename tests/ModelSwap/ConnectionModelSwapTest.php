<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Tests\Fixtures\CountedConnection;
use RoundlyConsulting\Connections\Tests\Fixtures\SwappedConnectionTestCase;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/**
 * The model-swap proof (S) for the `connections.model` seam, driven through the REAL
 * flows rather than a resolver string check.
 *
 * `tests/Support/ConnectionModelTest.php` already pins that the seam *validates* what it
 * is handed (it falls back to the packaged model for anything that could not answer the
 * package's queries) — that is domain behaviour and stays where it is. What it cannot
 * prove is the thing this file exists for: that the package actually *uses* the
 * configured class when a host drives a real connection.
 *
 * The swap is applied before boot by {@see SwappedConnectionTestCase}, which this
 * directory is bound to — Pest binds a test case per directory, not per file.
 */
it('honours a host connection model through every connection flow', function (): void {
    expect('connections.model')->toHonourModelSwap(CountedConnection::class, function (): array {
        $user = User::create();
        $team = Team::create();
        $other = Team::create();

        // A connect, an invite and a reconnect — the flows a host actually calls.
        $connected = $user->connectTo($team, ['view', 'edit']);
        $invited = $user->inviteConnection($other);
        $user->disconnectFrom($team);
        $reconnected = $user->reconnectTo($team);

        return [
            $connected,
            $invited,
            $reconnected,
            // The morph relation hydrates through the seam too, not just the writes.
            ...$user->connections()->get()->all(),
            ...$team->connectors()->get()->all(),
        ];
    });
});

/**
 * The swap must survive the path a host depends on most: the permission check. If the
 * check queried the packaged model while the writes went through the host's, access would
 * be decided from a different read than the grants that were written.
 */
it('answers permission checks through the swapped model', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['view']);

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($team, 'delete'))->toBeFalse()
        ->and($user->connections()->first())->toBeInstanceOf(CountedConnection::class)
        ->and($user->isConnectedTo($team))->toBeTrue();
});

// The structural half of the seam — Connection is non-final, and `connections.model`
// really defaults to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
