<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Testing;

use PHPUnit\Framework\Assert;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\PendingConnection;

/**
 * A pass-through ConnectionManager that records every operation so tests can
 * assert against intent (in the spirit of Bus::fake()). Real queries still run,
 * so reads behave normally.
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
        return new RecordingPendingConnection($this, $connector, $connectable);
    }

    public function from(Connectable $connector): PendingConnection
    {
        return new RecordingPendingConnection($this, $connector);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function record(string $verb, Connectable $connector, ?Connectable $connectable, array $permissions = []): void
    {
        $this->recorded[] = new RecordedOperation($verb, $connector, $connectable, $permissions);
    }

    /** @return list<RecordedOperation> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function assertConnected(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertTrue(
            $this->hasOperation('connect', $connector, $connectable)
                || $this->hasOperation('invite', $connector, $connectable),
            'Failed asserting that a connection was recorded between the given models.',
        );
    }

    public function assertNotConnected(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertFalse(
            $this->hasOperation('connect', $connector, $connectable)
                || $this->hasOperation('invite', $connector, $connectable),
            'Failed asserting that no connection was recorded between the given models.',
        );
    }

    public function assertHasPermissionThrough(Connectable $connector, Connectable $connectable, string $permission): void
    {
        $matched = false;

        foreach ($this->recorded as $operation) {
            if ($operation->matches($operation->verb, $connector, $connectable) && $operation->hasPermission($permission)) {
                $matched = true;
                break;
            }
        }

        Assert::assertTrue(
            $matched,
            "Failed asserting that permission [{$permission}] was recorded between the given models.",
        );
    }

    public function assertInvited(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertTrue(
            $this->hasOperation('invite', $connector, $connectable),
            'Failed asserting that an invitation was recorded between the given models.',
        );
    }

    public function assertAccepted(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertTrue(
            $this->hasOperation('accept', $connector, $connectable),
            'Failed asserting that an acceptance was recorded between the given models.',
        );
    }

    public function assertBlocked(Connectable $connector, Connectable $connectable): void
    {
        Assert::assertTrue(
            $this->hasOperation('block', $connector, $connectable),
            'Failed asserting that a block was recorded between the given models.',
        );
    }

    public function assertConnectedTimes(int $count): void
    {
        $actual = 0;

        foreach ($this->recorded as $operation) {
            if ($operation->verb === 'connect' || $operation->verb === 'invite') {
                $actual++;
            }
        }

        Assert::assertSame(
            $count,
            $actual,
            "Failed asserting that {$count} connection(s) were recorded; recorded {$actual}.",
        );
    }

    private function hasOperation(string $verb, Connectable $connector, ?Connectable $connectable): bool
    {
        foreach ($this->recorded as $operation) {
            if ($operation->matches($verb, $connector, $connectable)) {
                return true;
            }
        }

        return false;
    }
}
