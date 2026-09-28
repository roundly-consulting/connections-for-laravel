<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Connections\Actions\PruneConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Support\ConnectionModel;

/**
 * The root of the `Connections` facade and the injectable entry point to the
 * package: every host-facing action is reachable from here, either flat or
 * through the pair builder and its `permissions()` sub-accessor.
 */
class ConnectionManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Start a fluent connection builder between two models.
     */
    public function between(Connectable $connector, Connectable $connectable): PendingConnection
    {
        return new PendingConnection($this->container, $connector, $connectable);
    }

    /**
     * Start a fluent connection builder from a connector; set the connectable
     * later via to(), stage many via toMany(), or reconcile with sync().
     */
    public function from(Connectable $connector): PendingConnection
    {
        return new PendingConnection($this->container, $connector);
    }

    /**
     * Every live connection (of any connector) whose expiry falls within the
     * next `$days` days.
     *
     * @return Builder<Connection>
     */
    public function expiring(int $days = 7): Builder
    {
        return ConnectionModel::class()::query()->expiringSoon($days);
    }

    /**
     * Soft-delete every expired connection and return how many were removed.
     */
    public function prune(): int
    {
        return $this->container->make(PruneConnections::class)->execute();
    }

    /**
     * Drop every connection resolved into the in-request permission cache.
     */
    public function flushCache(): void
    {
        Cache::flush();
    }
}
