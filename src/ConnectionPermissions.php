<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Actions\SyncPermissions;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;

/**
 * The permission set on one connector → connectable pair, reached through
 * `Connections::between($a, $b)->permissions()`. Writes resolve their action
 * from the container; reads go through the connector's cache-aware checks.
 *
 * Not final: the recording fake extends it.
 */
readonly class ConnectionPermissions
{
    public function __construct(
        protected Container $container,
        protected Connectable $connector,
        protected Connectable $connectable,
    ) {}

    /**
     * Add permissions to the connection, creating it when absent.
     */
    public function grant(string ...$permissions): Connection
    {
        return $this->container->make(GrantPermissions::class)
            ->execute($this->connector, $this->connectable, ...array_values($permissions));
    }

    /**
     * Remove permissions from the connection.
     *
     * @throws ConnectionNotFound
     */
    public function revoke(string ...$permissions): Connection
    {
        return $this->container->make(RevokePermissions::class)
            ->execute($this->connector, $this->connectable, ...array_values($permissions));
    }

    /**
     * Replace the connection's permissions with exactly this set, creating
     * the connection when absent.
     */
    public function sync(string ...$permissions): Connection
    {
        return $this->container->make(SyncPermissions::class)
            ->execute($this->connector, $this->connectable, ...array_values($permissions));
    }

    /**
     * Remove every permission from the connection (sugar for `sync()` with none).
     */
    public function clear(): Connection
    {
        return $this->sync();
    }

    /**
     * The permissions stored on the connection (empty when there is none).
     *
     * @return Collection<int, string>
     */
    public function all(): Collection
    {
        return $this->connector->permissionsThroughConnection($this->connectable);
    }

    /**
     * Whether the connection grants the permission (wildcards honoured, and an
     * active connection required while `enforce_active_on_check` is on).
     */
    public function has(string $permission): bool
    {
        return $this->connector->hasPermissionThroughConnection($this->connectable, $permission);
    }

    public function hasAny(string ...$permissions): bool
    {
        return $this->connector->hasAnyPermissionThroughConnection($this->connectable, ...array_values($permissions));
    }

    public function hasAll(string ...$permissions): bool
    {
        return $this->connector->hasAllPermissionsThroughConnection($this->connectable, ...array_values($permissions));
    }
}
