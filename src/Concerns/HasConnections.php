<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\DisconnectConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Actions\SyncPermissions;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;

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
        return Cache::keyFor($this, $connectable);
    }

    public function hasPermissionThroughConnection(Connectable $connectable, string $permission, bool $force = false): bool
    {
        $key = $this->connectionCacheKey($connectable);

        if (! Cache::enabled() || $force || ! Cache::has($key)) {
            $connection = $this->connections()
                ->where('connectable_id', $connectable->getKey())
                ->where('connectable_type', $connectable->getMorphClass())
                ->first();

            if (! Cache::enabled()) {
                return $connection instanceof Connection && $connection->hasPermission($permission);
            }

            Cache::put($key, $connection);
        }

        $connection = Cache::get($key);

        return $connection instanceof Connection && $connection->hasPermission($permission);
    }

    /**
     * Create or update a connection from this model to another.
     *
     * @param  Collection<int, string>|list<string>|null  $permissions
     */
    public function connectTo(Connectable $connectable, Collection|array|null $permissions = null, ?CarbonInterface $expiresAt = null): Connection
    {
        return app(CreateConnection::class)->execute(
            $this,
            $connectable,
            $this->toPermissionCollection($permissions),
            $expiresAt,
        );
    }

    public function disconnectFrom(Connectable $connectable): void
    {
        app(DisconnectConnection::class)->execute($this, $connectable);
    }

    public function grantThroughConnection(Connectable $connectable, string ...$permissions): Connection
    {
        return app(GrantPermissions::class)->execute($this, $connectable, ...$permissions);
    }

    public function revokeThroughConnection(Connectable $connectable, string ...$permissions): Connection
    {
        return app(RevokePermissions::class)->execute($this, $connectable, ...$permissions);
    }

    /**
     * @param  Collection<int, string>|list<string>  $permissions
     */
    public function syncConnectionPermissions(Connectable $connectable, Collection|array $permissions): Connection
    {
        return app(SyncPermissions::class)->execute($this, $connectable, ...$this->toPermissionList($permissions));
    }

    /** @return Collection<int, string> */
    public function permissionsThroughConnection(Connectable $connectable): Collection
    {
        $connection = $this->connections()
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass())
            ->first();

        return $connection instanceof Connection ? $connection->permissions : new Collection;
    }

    /**
     * @param  Collection<int, string>|list<string>|null  $permissions
     * @return Collection<int, string>
     */
    private function toPermissionCollection(Collection|array|null $permissions): Collection
    {
        if ($permissions instanceof Collection) {
            return $permissions;
        }

        return new Collection($permissions ?? []);
    }

    /**
     * @param  Collection<int, string>|list<string>  $permissions
     * @return list<string>
     */
    private function toPermissionList(Collection|array $permissions): array
    {
        $values = $permissions instanceof Collection ? $permissions->all() : $permissions;

        return array_values(array_map(static fn (mixed $permission): string => (string) $permission, $values));
    }

    /** @return class-string<Connection> */
    protected function connectionModel(): string
    {
        $model = config('connections.model', Connection::class);

        if (is_string($model) && is_a($model, Connection::class, true)) {
            return $model;
        }

        return Connection::class;
    }
}
