<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use RoundlyConsulting\Connections\Actions\PruneConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;

class ConnectionManager
{
    /**
     * Start a fluent connection builder between two models.
     */
    public function between(Connectable $connector, Connectable $connectable): PendingConnection
    {
        return new PendingConnection($connector, $connectable);
    }

    /**
     * Start a fluent connection builder from a connector; set the connectable later via to().
     */
    public function from(Connectable $connector): PendingConnection
    {
        return new PendingConnection($connector);
    }

    /**
     * Soft-delete every expired connection and return how many were removed.
     */
    public function prune(): int
    {
        return app(PruneConnections::class)->execute();
    }
}
