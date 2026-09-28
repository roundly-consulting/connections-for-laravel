<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\GuardsStatusTransitions;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionAccepted;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class AcceptConnection
{
    use DispatchesConnectionEvents;
    use GuardsStatusTransitions;
    use ResolvesConnections;

    /**
     * Move a connection to the accepted state — from pending (accepting an
     * invitation) or from blocked: this is the one explicit unblock, the
     * connect-side verbs never lift a block. A no-op (no event) when the
     * connection is already accepted.
     *
     * @throws ConnectionNotFound
     * @throws InvalidStatusTransition
     */
    public function execute(Connectable $connector, Connectable $connectable): Connection
    {
        $connection = $this->findOrFail($connector, $connectable);

        if ($connection->isAccepted()) {
            return $connection;
        }

        $this->guardTransition($connection->status, ConnectionStatus::Accepted);

        $connection->update(['status' => ConnectionStatus::Accepted]);

        $this->invalidateCache($connector, $connectable);

        $this->dispatch(new ConnectionAccepted($connection));

        return $connection;
    }
}
