<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class RevokePermissions
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    /**
     * Remove the given permissions from the connection. The read-modify-write
     * runs in one transaction on the connection model's database with the row
     * locked, so a concurrent grant cannot bring a revoked permission back.
     *
     * @throws ConnectionNotFound
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        string ...$permissions,
    ): Connection {
        /** @var Connection $connection */
        $connection = $this->database()->transaction(function () use ($connector, $connectable, $permissions): Connection {
            $connection = $this->lockLive($connector, $connectable)
                ?? throw ConnectionNotFound::between($connector, $connectable);

            $previous = $this->toList($connection->permissions->all());
            $next = (new PermissionSet($previous))->remove(...$permissions);

            $connection->update(['permissions' => $next->toCollection()]);

            if ($previous !== $next->all()) {
                $this->dispatch(new ConnectionPermissionsChanged($connection, $previous, $next->all()));
            }

            return $connection;
        });

        $this->invalidateCache($connector, $connectable);

        return $connection;
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return list<string>
     */
    private function toList(array $permissions): array
    {
        return array_values(array_map(static fn (mixed $permission): string => (string) $permission, $permissions));
    }
}
