<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Testing;

use PHPUnit\Framework\Assert;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\PendingConnection;

/**
 * A pass-through ConnectionManager that records every mutating operation so
 * tests can assert against intent (in the spirit of Bus::fake()). Real queries
 * still run, so reads behave normally.
 *
 * Everything funnels through it — the facade, an injected ConnectionManager,
 * the `permissions()` sub-accessor and every `HasConnections` trait write.
 * Operations are recorded once they succeed; a verb that throws records nothing.
 *
 * This class lives in src/ so host apps can use it; it depends on PHPUnit's
 * Assert, which is always present in a Laravel app's dev dependencies. It is
 * never autoloaded outside a test run.
 */
final class ConnectionFake extends ConnectionManager
{
    /** @var list<RecordedOperation> */
    private array $recorded = [];

    public function between(Connectable $connector, Connectable $connectable): PendingConnection
    {
        return new RecordingPendingConnection($this, $this->container, $connector, $connectable);
    }

    public function from(Connectable $connector): PendingConnection
    {
        return new RecordingPendingConnection($this, $this->container, $connector);
    }

    public function prune(): int
    {
        $count = parent::prune();

        $this->record(new RecordedOperation('prune'));

        return $count;
    }

    /**
     * @internal called by the recording builder and sub-accessor
     */
    public function record(RecordedOperation $operation): void
    {
        $this->recorded[] = $operation;
    }

    /** @return list<RecordedOperation> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function assertNothingRecorded(): void
    {
        Assert::assertSame([], $this->recorded, sprintf(
            'Failed asserting that no connection operation was recorded; recorded [%s].',
            implode(', ', array_map(static fn (RecordedOperation $operation): string => $operation->verb, $this->recorded)),
        ));
    }

    // Connect / invite -----------------------------------------------------

    public function assertConnected(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertTrue(
            $this->has('connect', $connector, $connectable) || $this->has('invite', $connector, $connectable),
            'Failed asserting that a connection was recorded between the given models.',
        );
    }

    public function assertNotConnected(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertFalse(
            $this->has('connect', $connector, $connectable) || $this->has('invite', $connector, $connectable),
            'Failed asserting that no connection was recorded between the given models.',
        );
    }

    public function assertNothingConnected(): void
    {
        Assert::assertSame(0, $this->count('connect') + $this->count('invite'), 'Failed asserting that no connection was recorded.');
    }

    public function assertConnectedTimes(int $count): void
    {
        $actual = $this->count('connect') + $this->count('invite');

        Assert::assertSame(
            $count,
            $actual,
            "Failed asserting that {$count} connection(s) were recorded; recorded {$actual}.",
        );
    }

    public function assertInvited(Connectable $connector, Connectable $connectable): void
    {
        $this->assertVerb('invite', $connector, $connectable, 'an invitation');
    }

    public function assertNothingInvited(): void
    {
        $this->assertNoVerb('invite', 'invitation');
    }

    // Status -----------------------------------------------------------------

    public function assertAccepted(Connectable $connector, Connectable $connectable): void
    {
        $this->assertVerb('accept', $connector, $connectable, 'an acceptance');
    }

    public function assertNothingAccepted(): void
    {
        $this->assertNoVerb('accept', 'acceptance');
    }

    public function assertBlocked(Connectable $connector, Connectable $connectable): void
    {
        $this->assertVerb('block', $connector, $connectable, 'a block');
    }

    public function assertNothingBlocked(): void
    {
        $this->assertNoVerb('block', 'block');
    }

    // Lifecycle --------------------------------------------------------------

    public function assertDisconnected(Connectable $connector, Connectable $connectable): void
    {
        $this->assertVerb('disconnect', $connector, $connectable, 'a disconnection');
    }

    public function assertNothingDisconnected(): void
    {
        $this->assertNoVerb('disconnect', 'disconnection');
    }

    public function assertReconnected(Connectable $connector, Connectable $connectable): void
    {
        $this->assertVerb('reconnect', $connector, $connectable, 'a reconnection');
    }

    public function assertNothingReconnected(): void
    {
        $this->assertNoVerb('reconnect', 'reconnection');
    }

    public function assertExtended(Connectable $connector, Connectable $connectable): void
    {
        $this->assertVerb('extend', $connector, $connectable, 'an expiry change');
    }

    public function assertNothingExtended(): void
    {
        $this->assertNoVerb('extend', 'expiry change');
    }

    /**
     * Assert a `sync()` reconcile was recorded for the connector — and, when
     * `$connectables` is given, that its desired set was exactly those models.
     *
     * @param  iterable<int, Connectable>|null  $connectables
     */
    public function assertSynced(Connectable $connector, ?iterable $connectables = null): void
    {
        $expected = null;

        if ($connectables !== null) {
            $expected = [];

            foreach ($connectables as $connectable) {
                $expected[] = $connectable;
            }
        }

        Assert::assertTrue(
            $this->any(static fn (RecordedOperation $operation): bool => $operation->matches('sync', $connector)
                && ($expected === null || $operation->targetsAre($expected))),
            'Failed asserting that a connection sync was recorded for the given connector'.($expected === null ? '.' : ' with the given set.'),
        );
    }

    public function assertNothingSynced(): void
    {
        $this->assertNoVerb('sync', 'connection sync');
    }

    public function assertPruned(): void
    {
        Assert::assertTrue($this->count('prune') > 0, 'Failed asserting that a prune was recorded.');
    }

    public function assertNothingPruned(): void
    {
        $this->assertNoVerb('prune', 'prune');
    }

    // Permissions --------------------------------------------------------------

    public function assertGranted(Connectable $connector, Connectable $connectable, ?string $permission = null): void
    {
        $this->assertPermissionVerb('grant', $connector, $connectable, $permission, 'a grant');
    }

    public function assertNothingGranted(): void
    {
        $this->assertNoVerb('grant', 'grant');
    }

    public function assertRevoked(Connectable $connector, Connectable $connectable, ?string $permission = null): void
    {
        $this->assertPermissionVerb('revoke', $connector, $connectable, $permission, 'a revocation');
    }

    public function assertNothingRevoked(): void
    {
        $this->assertNoVerb('revoke', 'revocation');
    }

    /**
     * Assert a `permissions()->sync()` (or `clear()`) was recorded — and, when
     * `$permissions` is given, that it synced to exactly that set.
     *
     * @param  list<string>|null  $permissions
     */
    public function assertPermissionsSynced(Connectable $connector, Connectable $connectable, ?array $permissions = null): void
    {
        $expected = $permissions === null ? null : $this->normalised($permissions);

        Assert::assertTrue(
            $this->any(fn (RecordedOperation $operation): bool => $operation->matches('syncPermissions', $connector, $connectable)
                && ($expected === null || $this->normalised($operation->permissions) === $expected)),
            'Failed asserting that a permission sync was recorded between the given models'.($expected === null ? '.' : ' with the given set.'),
        );
    }

    public function assertPermissionsCleared(Connectable $connector, Connectable $connectable): void
    {
        $this->assertPermissionsSynced($connector, $connectable, []);
    }

    public function assertNothingPermissionsSynced(): void
    {
        $this->assertNoVerb('syncPermissions', 'permission sync');
    }

    /**
     * Assert any recorded operation between the pair carried the permission
     * (a connect with staged permissions, a grant, a sync …).
     */
    public function assertHasPermissionThrough(Connectable $connector, Connectable $connectable, string $permission): void
    {
        Assert::assertTrue(
            $this->any(static fn (RecordedOperation $operation): bool => $operation->matches($operation->verb, $connector, $connectable)
                && $operation->hasPermission($permission)),
            "Failed asserting that permission [{$permission}] was recorded between the given models.",
        );
    }

    // Helpers -------------------------------------------------------------------

    private function assertVerb(string $verb, Connectable $connector, Connectable $connectable, string $noun): void
    {
        Assert::assertTrue(
            $this->has($verb, $connector, $connectable),
            "Failed asserting that {$noun} was recorded between the given models.",
        );
    }

    private function assertPermissionVerb(string $verb, Connectable $connector, Connectable $connectable, ?string $permission, string $noun): void
    {
        Assert::assertTrue(
            $this->any(static fn (RecordedOperation $operation): bool => $operation->matches($verb, $connector, $connectable)
                && ($permission === null || $operation->hasPermission($permission))),
            $permission === null
                ? "Failed asserting that {$noun} was recorded between the given models."
                : "Failed asserting that {$noun} of [{$permission}] was recorded between the given models.",
        );
    }

    private function assertNoVerb(string $verb, string $noun): void
    {
        $count = $this->count($verb);

        Assert::assertSame(0, $count, "Failed asserting that no {$noun} was recorded; recorded {$count}.");
    }

    private function has(string $verb, Connectable $connector, Connectable $connectable): bool
    {
        return $this->any(static fn (RecordedOperation $operation): bool => $operation->matches($verb, $connector, $connectable));
    }

    private function count(string $verb): int
    {
        return count(array_filter($this->recorded, static fn (RecordedOperation $operation): bool => $operation->verb === $verb));
    }

    /**
     * @param  callable(RecordedOperation): bool  $predicate
     */
    private function any(callable $predicate): bool
    {
        foreach ($this->recorded as $operation) {
            if ($predicate($operation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function normalised(array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        sort($permissions);

        return $permissions;
    }
}
