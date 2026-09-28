<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Events\ConnectionRestored;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class RestoreConnection
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    public function __construct(
        private readonly CreateConnection $createConnection,
    ) {}

    /**
     * Restore a soft-deleted connection for the pair if one exists, otherwise
     * create a fresh connection. Restoring avoids colliding with the unique
     * morph index that a soft-deleted row still occupies.
     */
    public function execute(Connectable $connector, Connectable $connectable): Connection
    {
        $trashed = $this->findTrashed($connector, $connectable);

        if ($trashed === null) {
            return $this->createConnection->execute($connector, $connectable);
        }

        $trashed->restore();

        $this->invalidateCache($connector, $connectable);

        $this->dispatch(new ConnectionRestored($trashed));

        return $trashed;
    }
}
