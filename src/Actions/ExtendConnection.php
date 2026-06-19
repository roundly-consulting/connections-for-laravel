<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Carbon\CarbonInterface;
use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;

final class ExtendConnection
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    /**
     * Set (or clear, when null) the connection's expiry.
     *
     * @throws ConnectionNotFound
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        ?CarbonInterface $expiresAt,
    ): Connection {
        $connection = $this->findOrFail($connector, $connectable);

        $connection->update(['expires_at' => $expiresAt]);

        $this->invalidateCache($connector, $connectable);

        $this->dispatch(new ConnectionUpdated($connection));

        return $connection;
    }
}
