<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;

final readonly class BulkDisconnect
{
    use ResolvesConnections;

    public function __construct(
        private readonly DisconnectConnection $disconnectConnection,
    ) {}

    /**
     * Disconnect the connector from every connectable in one transaction on the
     * connection model's database, skipping any pair that has no live connection.
     *
     * @param  list<Connectable>  $connectables
     */
    public function execute(Connectable $connector, array $connectables): void
    {
        $this->database()->transaction(function () use ($connector, $connectables): void {
            foreach ($connectables as $connectable) {
                if ($this->find($connector, $connectable) === null) {
                    continue;
                }

                $this->disconnectConnection->execute($connector, $connectable);
            }
        });
    }
}
