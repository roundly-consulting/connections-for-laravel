<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\ConnectionData;
use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class GrantPermissions
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    public function __construct(
        private readonly CreateConnection $createConnection,
    ) {}

    /**
     * Add the given permissions to the connection, creating it if absent. The
     * read-modify-write runs in one transaction on the connection model's
     * database with the row locked, so a concurrent write to the pair can be
     * neither lost nor undone.
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
                // Should a concurrent writer create the pair first, the create
                // path adds to its permissions instead of overwriting them.
                return $this->createConnection->executeData(
                    ConnectionData::fromModels($connector, $connectable, PermissionSet::make(...$permissions), mergePermissions: true),
                    $connector,
                    $connectable,
                );
            }

            $previous = $this->toList($connection->permissions->all());
            $next = (new PermissionSet($previous))->add(...$permissions);

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
