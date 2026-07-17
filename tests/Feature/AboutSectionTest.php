<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`,
 * which returns `''`. Every "does not leak" check was vacuous — passing against empty
 * output.
 *
 * Connections carries no credential, which is exactly why it is worth pinning: the risk
 * here is the host's own **authorization vocabulary**. `connections.default_permissions`
 * holds the host's ability strings ("billing.refund", "admin.impersonate"), and the
 * provider is deliberately written to report how many are configured and never which —
 * naming them would enumerate a host's permission model in `about` output. This test is
 * what stops a future "helpful" change from rendering them.
 */
it('renders the connections section without leaking the host permission vocabulary', function (): void {
    config()->set('connections.default_permissions', ['billing.refund', 'admin.impersonate']);
    config()->set('connections.register_gate', true);

    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['secret.ability.name']);

    expect('connections')->toLeakNoSecrets(
        secrets: [
            // The host's ability strings are reported by count, never by name.
            'billing.refund',
            'admin.impersonate',
            // Nothing about actual connections or their grants belongs in `about`.
            'secret.ability.name',
        ],
        mustRender: [
            'Model',
            'Table',
            'Default status',
            'Access checks',
            'Default expiry',
            'Default permissions',
            'Gate integration',
            'In-request cache',
            'Events',
            // The positive halves that prove the lines are reporting rather than silently
            // empty — including the count itself, which is the permission row's whole
            // contribution once the names are withheld.
            '2 granted on connect',
            'ENFORCED',
            'connections',
        ],
    );
});
