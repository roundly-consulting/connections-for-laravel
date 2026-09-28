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
     * connection must not connect it.
     *
     * @throws ConnectionNotFound
     */
    public function execute(Connectable $connector, Connectable $connectable): Connection
    {
        $connection = $this->findOrFail($connector, $connectable);

        $previous = array_values(array_map(
            static fn (mixed $permission): string => (string) $permission,
            $connection->permissions->all(),
        ));

        $connection->update(['permissions' => []]);

        $this->invalidateCache($connector, $connectable);

        if ($previous !== []) {
            $this->dispatch(new ConnectionPermissionsChanged($connection, $previous, []));
        }

        return $connection;
    }
}
