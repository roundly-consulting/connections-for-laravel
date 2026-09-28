<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Support\ConnectionModel;

/**
 * Gives an Eloquent model the ability to form connections to other models,
 * carrying a set of permissions and an optional expiration.
 *
 * Every write delegates to the ConnectionManager (the `Connections` facade
 * root), so host overrides and `Connections::fake()` see trait calls too.
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
        return $this->connectionQuery($connectable)
            ->when($this->enforceActive(), fn ($query) => $query->active())
            ->exists();
    }

    public function isConnectedToAny(string $type): bool
    {
        return $this->connections()
            ->where('connectable_type', $type)
            ->when($this->enforceActive(), fn ($query) => $query->active())
            ->exists();
    }

    public function hasConnector(Connectable $connector): bool
    {
        return $this->connectors()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->when($this->enforceActive(), fn ($query) => $query->active())
            ->exists();
    }

    public function hasConnectorFromAny(string $type): bool
    {
        return $this->connectors()
            ->where('connector_type', $type)
            ->when($this->enforceActive(), fn ($query) => $query->active())
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
            $connection = $this->connectionQuery($connectable)->first();

            if (! Cache::enabled()) {
                return $this->grantsPermission($connection, $permission);
            }

            Cache::put($key, $connection);
        }

        $connection = Cache::get($key);

        return $this->grantsPermission($connection instanceof Connection ? $connection : null, $permission);
    }

    public function hasAnyPermissionThroughConnection(Connectable $connectable, string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermissionThroughConnection($connectable, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissionsThroughConnection(Connectable $connectable, string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermissionThroughConnection($connectable, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create or update a connection from this model to another.
     *
     * @param  Collection<int, string>|list<string>|null  $permissions
     * @param  array<string, mixed>|null  $meta
     */
    public function connectTo(Connectable $connectable, Collection|array|null $permissions = null, ?CarbonInterface $expiresAt = null, ?array $meta = null): Connection
    {
        $pending = app(ConnectionManager::class)->between($this, $connectable)->expiringAt($expiresAt);

        if ($permissions !== null) {
            $pending->withPermissions(...$this->toPermissionList($permissions));
        }

        if ($meta !== null) {
            $pending->withMeta($meta);
        }

        return $pending->connect();
    }

    public function disconnectFrom(Connectable $connectable): void
    {
        app(ConnectionManager::class)->between($this, $connectable)->disconnect();
    }

    /**
     * Disconnect from the connectable if connected, otherwise connect.
     *
     * @param  Collection<int, string>|list<string>|null  $permissions
     */
    public function toggleConnection(Connectable $connectable, Collection|array|null $permissions = null): ?Connection
    {
        $pending = app(ConnectionManager::class)->between($this, $connectable);

        if ($permissions !== null) {
            $pending->withPermissions(...$this->toPermissionList($permissions));
        }

        return $pending->toggle();
    }

    /**
     * Restore a soft-deleted connection for the pair, or connect afresh.
     */
    public function reconnectTo(Connectable $connectable): Connection
    {
        return app(ConnectionManager::class)->between($this, $connectable)->reconnect();
    }

    public function inviteConnection(Connectable $connectable): Connection
    {
        return app(ConnectionManager::class)->between($this, $connectable)->invite();
    }

    /**
     * Accept an invitation this model received from the given connector.
     */
    public function acceptConnectionFrom(Connectable $connector): Connection
    {
        return app(ConnectionManager::class)->between($connector, $this)->accept();
    }

    /**
     * Block a connection this model received from the given connector.
     */
    public function blockConnectionFrom(Connectable $connector): Connection
    {
        return app(ConnectionManager::class)->between($connector, $this)->block();
    }

    public function grantThroughConnection(Connectable $connectable, string ...$permissions): Connection
    {
        return app(ConnectionManager::class)->between($this, $connectable)->permissions()->grant(...$permissions);
    }

    public function revokeThroughConnection(Connectable $connectable, string ...$permissions): Connection
    {
        return app(ConnectionManager::class)->between($this, $connectable)->permissions()->revoke(...$permissions);
    }

    public function clearConnectionPermissions(Connectable $connectable): Connection
    {
        return app(ConnectionManager::class)->between($this, $connectable)->permissions()->clear();
    }

    /**
     * @param  Collection<int, string>|list<string>  $permissions
     */
    public function syncConnectionPermissions(Connectable $connectable, Collection|array $permissions): Connection
    {
        return app(ConnectionManager::class)->between($this, $connectable)->permissions()->sync(...$this->toPermissionList($permissions));
    }

    /**
     * Reconcile this model's connections to exactly the given set, connecting
     * any missing connectables and disconnecting any extras. Pass a SyncTarget
     * to give a connectable its own permissions, expiry or meta.
     *
     * @param  iterable<int|string, Connectable|SyncTarget>  $connectables
     */
    public function syncConnections(iterable $connectables): SyncResult
    {
        return app(ConnectionManager::class)->from($this)->sync($connectables);
    }

    /** @return Collection<int, string> */
    public function permissionsThroughConnection(Connectable $connectable): Collection
    {
        $connection = $this->connectionQuery($connectable)->first();

        return $connection instanceof Connection ? $connection->permissions : new Collection;
    }

    /** @return MorphMany<Connection, $this> */
    public function activeConnections(): MorphMany
    {
        return $this->connections()->active();
    }

    /** @return MorphMany<Connection, $this> */
    public function expiredConnections(): MorphMany
    {
        return $this->connections()->expired();
    }

    /** @return MorphMany<Connection, $this> */
    public function expiringConnections(int $days = 7): MorphMany
    {
        return $this->connections()->expiringSoon($days);
    }

    /** @return MorphMany<Connection, $this> */
    public function connectionsWithPermission(string $permission): MorphMany
    {
        return $this->connections()->withPermission($permission);
    }

    /**
     * The connectables this model is connected to of the given type. Accepts a
     * fully-qualified class name or a registered morph alias.
     *
     * @return Collection<int, Model>
     */
    public function connectablesOfType(string $class): Collection
    {
        $type = $this->resolveMorphType($class);

        /** @var Collection<int, Connection> $connections */
        $connections = $this->connections()
            ->where('connectable_type', $type)
            ->with('connectable')
            ->get();

        return $connections
            ->map(static fn (Connection $connection): ?Model => $connection->connectable)
            ->filter()
            ->values();
    }

    /**
     * The connectors connected to this model of the given type.
     *
     * @return Collection<int, Model>
     */
    public function connectorsOfType(string $class): Collection
    {
        $type = $this->resolveMorphType($class);

        /** @var Collection<int, Connection> $connections */
        $connections = $this->connectors()
            ->where('connector_type', $type)
            ->with('connector')
            ->get();

        return $connections
            ->map(static fn (Connection $connection): ?Model => $connection->connector)
            ->filter()
            ->values();
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

    /** @return MorphMany<Connection, $this> */
    private function connectionQuery(Connectable $connectable): MorphMany
    {
        return $this->connections()
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass());
    }

    private function grantsPermission(?Connection $connection, string $permission): bool
    {
        if (! $connection instanceof Connection) {
            return false;
        }

        if ($this->enforceActive() && ! $connection->isActive()) {
            return false;
        }

        return $connection->hasPermission($permission);
    }

    private function enforceActive(): bool
    {
        return (bool) config('connections.enforce_active_on_check', true);
    }

    private function resolveMorphType(string $class): string
    {
        // A registered morph alias is already the stored value.
        if (Relation::getMorphedModel($class) !== null) {
            return $class;
        }

        // A FQCN maps to its morph class (the alias when a morph map is set).
        if (is_a($class, Model::class, true)) {
            return (new $class)->getMorphClass();
        }

        return $class;
    }

    /**
     * A host may override this to hard-wire the model instead of configuring it.
     *
     * @return class-string<Connection>
     */
    protected function connectionModel(): string
    {
        return ConnectionModel::class();
    }
}
