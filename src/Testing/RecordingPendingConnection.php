<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Testing;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\ConnectionPermissions;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\PendingConnection;

/**
 * A PendingConnection that passes every verb through to the real builder (so
 * DB reads and writes work) and records it on the fake once it succeeds. Bulk
 * verbs record one operation per staged target.
 */
final class RecordingPendingConnection extends PendingConnection
{
    public function __construct(
        private readonly ConnectionFake $fake,
        Container $container,
        Connectable $connector,
        ?Connectable $connectable = null,
    ) {
        parent::__construct($container, $connector, $connectable);
    }

    /**
     * Recorded as an invitation when it created or re-staged one — a pending status was
     * staged, or nothing was and a new pair took a pending `default_status` — so
     * `assertInvited()` sees `asPending()->connect()` the same as `invite()`.
     */
    public function connect(): Connection
    {
        $mayInvite = $this->mayInvite($this->resolveConnectable());

        $connection = parent::connect();

        $this->fake->record(new RecordedOperation(
            $this->connectVerb($mayInvite, $connection),
            $this->connector,
            $this->connectable,
            $this->permissions ?? [],
        ));

        return $connection;
    }

    public function invite(): Connection
    {
        // Call the parent connect directly so the overridden connect() does not
        // record a second operation.
        $this->asPending();
        $connection = parent::connect();

        $this->fake->record(new RecordedOperation('invite', $this->connector, $this->connectable, $this->permissions ?? []));

        return $connection;
    }

    public function accept(): Connection
    {
        return $this->recorded('accept', parent::accept());
    }

    public function block(): Connection
    {
        return $this->recorded('block', parent::block());
    }

    public function disconnect(): void
    {
        parent::disconnect();

        $this->fake->record(new RecordedOperation('disconnect', $this->connector, $this->connectable));
    }

    public function reconnect(): Connection
    {
        return $this->recorded('reconnect', parent::reconnect());
    }

    public function extend(?CarbonInterface $expiresAt): Connection
    {
        return $this->recorded('extend', parent::extend($expiresAt));
    }

    public function permissions(): ConnectionPermissions
    {
        return new RecordingConnectionPermissions(
            $this->fake,
            $this->container,
            $this->connector,
            $this->resolveConnectable(),
        );
    }

    /**
     * @param  iterable<int|string, Connectable|SyncTarget>  $connectables
     */
    public function sync(iterable $connectables): SyncResult
    {
        $staged = [];

        foreach ($connectables as $connectable) {
            $staged[] = $connectable;
        }

        $result = parent::sync($staged);

        $this->fake->record(new RecordedOperation(
            'sync',
            $this->connector,
            targets: array_map(
                static fn (Connectable|SyncTarget $target): Connectable => $target instanceof SyncTarget ? $target->model : $target,
                $staged,
            ),
        ));

        return $result;
    }

    /**
     * @return Collection<int, Connection>
     */
    public function connectAll(): Collection
    {
        $mayInvite = array_map(fn (Connectable $connectable): bool => $this->mayInvite($connectable), $this->connectables);

        $connections = parent::connectAll();

        foreach ($this->connectables as $index => $connectable) {
            $this->fake->record(new RecordedOperation(
                $this->connectVerb($mayInvite[$index], $connections->get($index)),
                $this->connector,
                $connectable,
                $this->permissions ?? [],
            ));
        }

        return $connections;
    }

    public function disconnectAll(): void
    {
        parent::disconnectAll();

        $this->recordEachTarget('disconnect');
    }

    /**
     * @return Collection<int, Connection>
     */
    public function grantAll(string ...$permissions): Collection
    {
        $connections = parent::grantAll(...$permissions);

        $this->recordEachTarget('grant', $permissions === [] ? $this->permissions ?? [] : array_values($permissions));

        return $connections;
    }

    /**
     * @return Collection<int, Connection>
     */
    public function revokeAll(string ...$permissions): Collection
    {
        $connections = parent::revokeAll(...$permissions);

        $this->recordEachTarget('revoke', array_values($permissions));

        return $connections;
    }

    /**
     * Whether connecting to the connectable can create an invitation: a pending status
     * is staged, or none is and the pair has no live row (the configured default then
     * decides). Read before the write, which is what tells a new pair from an update.
     */
    private function mayInvite(Connectable $connectable): bool
    {
        return $this->status === ConnectionStatus::Pending
            || ($this->status === null && $this->storedConnection($connectable) === null);
    }

    private function connectVerb(bool $mayInvite, ?Connection $connection): string
    {
        return $mayInvite && $connection?->isPending() === true ? 'invite' : 'connect';
    }

    private function recorded(string $verb, Connection $connection): Connection
    {
        $this->fake->record(new RecordedOperation($verb, $this->connector, $this->connectable));

        return $connection;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function recordEachTarget(string $verb, array $permissions = []): void
    {
        foreach ($this->connectables as $connectable) {
            $this->fake->record(new RecordedOperation($verb, $this->connector, $connectable, $permissions));
        }
    }
}
