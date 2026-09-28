<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\GuardsStatusTransitions;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConfigDefaults;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\ConnectionData;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionInvited;
use RoundlyConsulting\Connections\Events\ConnectionRestored;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class CreateConnection
{
    use DispatchesConnectionEvents;
    use GuardsStatusTransitions;
    use ResolvesConfigDefaults;
    use ResolvesConnections;

    /**
     * Create a connection between two models, or update the pair's existing one.
     *
     * - **New pair** — every attribute the caller leaves null falls back to its
     *   config default (`default_permissions`, `expiry.default`, `default_status`).
     * - **Existing live row** — only what the caller supplies changes: a null
     *   permissions / expiry / status / meta keeps the stored value (meta is
     *   merged unless $replaceMeta). An explicit status must pass
     *   ConnectionStatus::canTransitionTo(), and a block is never lifted here —
     *   only AcceptConnection may.
     * - **Soft-deleted row** (after a disconnect or prune it still holds the
     *   unique morph index) — revived as a fresh connection with the new-pair
     *   rules, except a blocked row, which is restored still blocked.
     *
     * @param  Collection<int, string>|null  $permissions
     * @param  array<string, mixed>|null  $meta
     *
     * @throws InvalidStatusTransition
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        ?Collection $permissions = null,
        ?CarbonInterface $expiresAt = null,
        ?ConnectionStatus $status = null,
        ?array $meta = null,
        bool $replaceMeta = false,
    ): Connection {
        return $this->executeData(
            ConnectionData::fromModels($connector, $connectable, $permissions, $expiresAt, $status, $meta, $replaceMeta),
            $connector,
            $connectable,
        );
    }

    /**
     * @throws InvalidStatusTransition
     */
    public function executeData(
        ConnectionData $data,
        Connectable $connector,
        Connectable $connectable,
    ): Connection {
        /** @var array{Connection, list<object>} $written */
        $written = $this->query()->getModel()->getConnection()->transaction(
            fn (): array => $this->write($data, $connector, $connectable),
        );

        [$connection, $events] = $written;

        $this->invalidateCache($connector, $connectable);

        foreach ($events as $event) {
            $this->dispatch($event);
        }

        return $connection;
    }

    /**
     * @return array{Connection, list<object>}
     */
    private function write(ConnectionData $data, Connectable $connector, Connectable $connectable): array
    {
        $existing = $this->findAnyForUpdate($connector, $connectable);

        if ($existing !== null) {
            return $this->applyTo($existing, $data);
        }

        try {
            /** @var Connection $connection */
            $connection = $this->query()->withSavepointIfNeeded(fn (): Connection => $this->query()->create([
                ...$data->connectorKeys(),
                ...$data->connectableKeys(),
                ...$this->freshAttributes($data),
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent writer created the pair between the lookup and this
            // insert: apply this write to its row, under the same status rules.
            $existing = $this->findAnyForUpdate($connector, $connectable) ?? throw $exception;

            return $this->applyTo($existing, $data);
        }

        return [$connection, $this->createdEvents($connection)];
    }

    /**
     * @return array{Connection, list<object>}
     */
    private function applyTo(Connection $connection, ConnectionData $data): array
    {
        if (! $connection->trashed()) {
            $this->update($connection, $data);

            return [$connection, [new ConnectionUpdated($connection)]];
        }

        if ($connection->isBlocked()) {
            // A block outlives a disconnect or prune: restore the row still
            // blocked rather than handing the pair a fresh start.
            $connection->deleted_at = null;
            $this->update($connection, $data);

            return [$connection, [new ConnectionRestored($connection)]];
        }

        $connection->forceFill([
            ...$this->freshAttributes($data),
            'deleted_at' => null,
        ]);
        $connection->setCreatedAt($connection->freshTimestamp());
        $connection->save();

        return [$connection, $this->createdEvents($connection)];
    }

    /**
     * Change only what the caller supplied.
     *
     * @throws InvalidStatusTransition
     */
    private function update(Connection $connection, ConnectionData $data): void
    {
        if ($data->status !== null) {
            $this->guardConnectTransition($connection->status, $data->status);

            $connection->status = $data->status;
        }

        if ($data->permissions !== null) {
            $connection->permissions = $data->permissions->toCollection();
        }

        if ($data->expiresAt !== null) {
            $connection->expires_at = $data->expiresAt;
        }

        $connection->meta = $this->resolveMeta($connection->meta, $data->meta, $data->replaceMeta);

        $connection->save();
    }

    /**
     * A new connection's attributes: what the caller supplied, config defaults
     * for the rest.
     *
     * @return array{permissions: Collection<int, string>, expires_at: CarbonInterface|null, status: ConnectionStatus, meta: array<string, mixed>|null}
     */
    private function freshAttributes(ConnectionData $data): array
    {
        return [
            'permissions' => $this->resolvePermissions($data->permissions)->toCollection(),
            'expires_at' => $this->resolveExpiry($data->expiresAt),
            'status' => $this->resolveStatus($data->status),
            'meta' => $data->meta,
        ];
    }

    /**
     * @return list<object>
     */
    private function createdEvents(Connection $connection): array
    {
        return $connection->isPending()
            ? [new ConnectionCreated($connection), new ConnectionInvited($connection)]
            : [new ConnectionCreated($connection)];
    }

    /**
     * Resolve the meta to persist. By default supplied meta is shallow-merged
     * over the stored meta; when $replace is true it overwrites it. A null
     * supplied meta leaves the stored meta untouched.
     *
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    private function resolveMeta(?array $current, ?array $meta, bool $replace): ?array
    {
        if ($meta === null) {
            return $current;
        }

        if ($replace || $current === null) {
            return $meta;
        }

        return array_merge($current, $meta);
    }
}
