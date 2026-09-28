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
     * Remove the given permissions from the connection.
     *
     * @throws ConnectionNotFound
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        string ...$permissions,
    ): Connection {
        $connection = $this->findOrFail($connector, $connectable);

        $previous = $this->toList($connection->permissions->all());
        $next = (new PermissionSet($previous))->remove(...$permissions);

        $connection->update(['permissions' => $next->toCollection()]);

        $this->invalidateCache($connector, $connectable);

        if ($previous !== $next->all()) {
            $this->dispatch(new ConnectionPermissionsChanged($connection, $previous, $next->all()));
        }

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
