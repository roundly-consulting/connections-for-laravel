<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Connection;
use RoundlyConsulting\Connections\Interfaces\Connectable;

/**
 * Gives an Eloquent model the ability to form connections to other models,
 * carrying a set of permissions and an optional expiration.
 */
trait HasConnections
{
    /** @return MorphMany<Connection, $this> */
    public function connections(): MorphMany
    {
        return $this->morphMany($this->connectionModel(), 'connector');
    }

    /** @return MorphMany<Connection, $this> */
    public function connectors(): MorphMany
    {
        return $this->morphMany($this->connectionModel(), 'connectable');
    }

    public function isConnectedTo(Connectable $connectable): bool
    {
        return $this->connections()
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass())
            ->exists();
    }

    public function isConnectedToAny(string $type): bool
    {
        return $this->connections()
            ->where('connectable_type', $type)
            ->exists();
    }

    public function hasConnector(Connectable $connector): bool
    {
        return $this->connectors()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->exists();
    }

    public function hasConnectorFromAny(string $type): bool
    {
        return $this->connectors()
            ->where('connector_type', $type)
            ->exists();
    }

    public function connectionCacheKey(Connectable $connectable): string
    {
        return implode('#', [
            'Connector',
            $this->getKey(),
            'Connectable',
            $connectable->getKey(),
        ]);
    }

    public function hasPermissionThroughConnection(Connectable $connectable, string $permission, bool $force = false): bool
    {
        $key = $this->connectionCacheKey($connectable);

        if (! Cache::has($key) || $force) {
            $connection = $this->connections()
                ->where('connectable_id', $connectable->getKey())
                ->where('connectable_type', $connectable->getMorphClass())
                ->first();

            Cache::put($key, $connection);
        }

        $connection = Cache::get($key);

        return $connection instanceof Connection && $connection->hasPermission($permission);
    }

    /** @return class-string<Connection> */
    protected function connectionModel(): string
    {
        /** @var class-string<Connection> $model */
        $model = config('connections.model', Connection::class);

        return $model;
    }
}
