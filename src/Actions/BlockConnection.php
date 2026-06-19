<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionBlocked;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;

final class BlockConnection
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    /**
     * Move a connection to the blocked state. A no-op (no event) when the
     * connection is already blocked.
     *
     * @throws ConnectionNotFound
     */
    public function execute(Connectable $connector, Connectable $connectable): Connection
    {
        $connection = $this->findOrFail($connector, $connectable);

        if ($connection->isBlocked()) {
            return $connection;
        }

        $connection->update(['status' => ConnectionStatus::Blocked]);

        $this->invalidateCache($connector, $connectable);

        $this->dispatch(new ConnectionBlocked($connection));

        return $connection;
    }
}
