<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class SyncPermissions
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    public function __construct(
        private readonly CreateConnection $createConnection,
    ) {}

    /**
     * Replace the connection's permissions with the exact given set,
     * creating the connection if absent. Runs in one transaction on the
     * connection model's database with the row locked.
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        string ...$permissions,
    ): Connection {
        /** @var Connection $connection */
        $connection = $this->database()->transaction(function () use ($connector, $connectable, $permissions): Connection {
            $connection = $this->lockLive($connector, $connectable);

            if ($connection === null) {
                return $this->createConnection->execute(
                    $connector,
                    $connectable,
                    PermissionSet::make(...$permissions)->toCollection(),
                );
            }

            $previous = $this->toList($connection->permissions->all());
            $next = PermissionSet::make(...$permissions);

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
