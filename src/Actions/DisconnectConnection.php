<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;

final class DisconnectConnection
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    /**
     * Soft-delete the connection between two models.
     *
     * @throws ConnectionNotFound
     */
    public function execute(Connectable $connector, Connectable $connectable): void
    {
        $connection = $this->findOrFail($connector, $connectable);

        $connection->delete();

        $this->invalidateCache($connector, $connectable);

        $this->dispatch(new ConnectionRemoved($connection));
    }
}
