<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class ClearPermissions
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    /**
     * Remove every permission from the connection. Unlike SyncPermissions it
     * never creates one: removing permissions from a pair that has no live
     * connection must not connect it. Runs in one transaction on the
     * connection model's database with the row locked.
     *
     * @throws ConnectionNotFound
     */
    public function execute(Connectable $connector, Connectable $connectable): Connection
    {
        /** @var Connection $connection */
        $connection = $this->database()->transaction(function () use ($connector, $connectable): Connection {
            $connection = $this->lockLive($connector, $connectable)
                ?? throw ConnectionNotFound::between($connector, $connectable);

            $previous = array_values(array_map(
                static fn (mixed $permission): string => (string) $permission,
                $connection->permissions->all(),
            ));

            $connection->update(['permissions' => []]);

            if ($previous !== []) {
                $this->dispatch(new ConnectionPermissionsChanged($connection, $previous, []));
            }

            return $connection;
        });

        $this->invalidateCache($connector, $connectable);

        return $connection;
    }
}
