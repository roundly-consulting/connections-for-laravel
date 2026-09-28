<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConfigDefaults;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\ConnectionData;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionInvited;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class CreateConnection
{
    use DispatchesConnectionEvents;
    use ResolvesConfigDefaults;
    use ResolvesConnections;

    /**
     * Create or update a connection between two models.
     *
     * Passing null for $permissions falls back to the configured default
     * permissions; an explicit (even empty) Collection is honoured as-is.
     *
     * @param  Collection<int, string>|null  $permissions
     * @param  array<string, mixed>|null  $meta
     */
    public function execute(
        Connectable $connector,
        Connectable $connectable,
        ?Collection $permissions = null,
        ?CarbonInterface $expiresAt = null,
        ?ConnectionStatus $status = null,
        ?array $meta = null,
        bool $replaceMeta = false,
    ): Connection {
        return $this->executeData(
            ConnectionData::fromModels($connector, $connectable, $permissions, $expiresAt, $status, $meta, $replaceMeta),
            $connector,
            $connectable,
        );
    }

    public function executeData(
        ConnectionData $data,
        Connectable $connector,
        Connectable $connectable,
    ): Connection {
        $existing = $this->find($connector, $connectable);
        $existed = $existing !== null;

        $permissions = $this->resolvePermissions($data->permissions);
        $status = $this->resolveStatus($data->status);
        $expiresAt = $this->resolveExpiry($data->expiresAt);
        $meta = $this->resolveMeta($existing, $data->meta, $data->replaceMeta);

        /** @var Connection $connection */
        $connection = $this->query()->updateOrCreate(
            [
                ...$data->connectorKeys(),
                ...$data->connectableKeys(),
            ],
            [
                'permissions' => $permissions->toCollection(),
                'expires_at' => $expiresAt,
                'status' => $status,
                'meta' => $meta,
            ],
        );

        $this->invalidateCache($connector, $connectable);

        if ($existed) {
            $this->dispatch(new ConnectionUpdated($connection));
        } else {
            $this->dispatch(new ConnectionCreated($connection));

            if ($connection->isPending()) {
                $this->dispatch(new ConnectionInvited($connection));
            }
        }

        return $connection;
    }

    /**
     * Resolve the meta to persist. By default supplied meta is shallow-merged
     * over any existing meta; when $replace is true it overwrites it. A null
     * supplied meta leaves existing meta untouched.
     *
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    private function resolveMeta(?Connection $existing, ?array $meta, bool $replace): ?array
    {
        if ($meta === null) {
            return $existing?->meta;
        }

        if ($replace) {
            return $meta;
        }

        $current = $existing?->meta;

        return $current === null ? $meta : array_merge($current, $meta);
    }
}
