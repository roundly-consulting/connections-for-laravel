<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\ConnectionData;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Models\Connection;

final class CreateConnection
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    /**
     * Create or update a connection between two models.
     *
     * @param  Collection<int, string>|null  $permissions
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        ?Collection $permissions = null,
        ?CarbonInterface $expiresAt = null,
    ): Connection {
        return $this->executeData(
            ConnectionData::fromModels($connector, $connectable, $permissions, $expiresAt),
            $connector,
            $connectable,
        );
    }

    public function executeData(
        ConnectionData $data,
        Connectable $connector,
        Connectable $connectable,
    ): Connection {
        $existed = $this->find($connector, $connectable) !== null;

        /** @var Connection $connection */
        $connection = $this->query()->updateOrCreate(
            [
                ...$data->connectorKeys(),
                ...$data->connectableKeys(),
            ],
            [
                'permissions' => $data->permissions->toCollection(),
                'expires_at' => $data->expiresAt,
            ],
        );

        $this->invalidateCache($connector, $connectable);

        $this->dispatch($existed
            ? new ConnectionUpdated($connection)
            : new ConnectionCreated($connection));

        return $connection;
    }
}
