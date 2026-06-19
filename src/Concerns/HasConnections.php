<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RoundlyConsulting\Connections\Actions\AcceptConnection;
use RoundlyConsulting\Connections\Actions\BlockConnection;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\DisconnectConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Actions\RestoreConnection;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Actions\SyncConnections;
use RoundlyConsulting\Connections\Actions\SyncPermissions;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
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
        return app(CreateConnection::class)->execute(
            $this,
            $connectable,
            $permissions === null ? null : $this->toPermissionCollection($permissions),
            $expiresAt,
            null,
            $meta,
        );
    }

    public function disconnectFrom(Connectable $connectable): void
    {
        app(DisconnectConnection::class)->execute($this, $connectable);
    }

    /**
     * Disconnect from the connectable if connected, otherwise connect.
     *
     * @param  Collection<int, string>|list<string>|null  $permissions
     */
    public function toggleConnection(Connectable $connectable, Collection|array|null $permissions = null): ?Connection
    {
        if ($this->isConnectedTo($connectable)) {
            $this->disconnectFrom($connectable);

            return null;
        }

        return $this->connectTo($connectable, $permissions);
    }

    /**
     * Restore a soft-deleted connection for the pair, or connect afresh.
     */
    public function reconnectTo(Connectable $connectable): Connection
    {
        return app(RestoreConnection::class)->execute($this, $connectable);
    }

    public function inviteConnection(Connectable $connectable): Connection
    {
        return app(CreateConnection::class)->execute(
            $this,
            $connectable,
            null,
            null,
            ConnectionStatus::Pending,
        );
    }

    /**
     * Accept an invitation this model received from the given connector.
     */
    public function acceptConnectionFrom(Connectable $connector): Connection
    {
        return app(AcceptConnection::class)->execute($connector, $this);
    }

    /**
     * Block a connection this model received from the given connector.
     */
    public function blockConnectionFrom(Connectable $connector): Connection
    {
        return app(BlockConnection::class)->execute($connector, $this);
    }

    public function grantThroughConnection(Connectable $connectable, string ...$permissions): Connection
    {
        return app(GrantPermissions::class)->execute($this, $connectable, ...$permissions);
    }

    public function revokeThroughConnection(Connectable $connectable, string ...$permissions): Connection
    {
        return app(RevokePermissions::class)->execute($this, $connectable, ...$permissions);
    }

    public function clearConnectionPermissions(Connectable $connectable): Connection
    {
        return app(SyncPermissions::class)->execute($this, $connectable);
    }

    /**
     * @param  Collection<int, string>|list<string>  $permissions
     */
    public function syncConnectionPermissions(Connectable $connectable, Collection|array $permissions): Connection
    {
        return app(SyncPermissions::class)->execute($this, $connectable, ...$this->toPermissionList($permissions));
    }

    /**
     * Reconcile this model's connections to exactly the given set, connecting
     * any missing connectables and disconnecting any extras.
     *
     * Accepts a list of Connectable models, or a map keyed by anything where
     * each value is a SyncTarget or an attribute array
     * (['model' => Connectable, 'permissions' => ..., 'expires_at' => ..., 'meta' => ...]).
     *
     * @param  iterable<int|string, Connectable|SyncTarget|array<string, mixed>>  $connectables
     */
    public function syncConnections(iterable $connectables): SyncResult
    {
        $targets = [];

        foreach ($connectables as $value) {
            $targets[] = $this->normalizeSyncTarget($value);
        }

        return app(SyncConnections::class)->execute($this, $targets);
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

    /**
     * @param  Connectable|SyncTarget|array<string, mixed>  $value
     */
    private function normalizeSyncTarget(Connectable|SyncTarget|array $value): SyncTarget
    {
        if ($value instanceof SyncTarget) {
            return $value;
        }

        if ($value instanceof Connectable) {
            return new SyncTarget($value);
        }

        $model = $value['model'] ?? null;

        if (! $model instanceof Connectable) {
            throw new InvalidArgumentException('Each syncConnections target array must include a "model" Connectable.');
        }

        $permissions = $value['permissions'] ?? null;
        $expiresAt = $value['expires_at'] ?? null;
        $meta = $value['meta'] ?? null;

        return new SyncTarget(
            model: $model,
            permissions: is_array($permissions) ? array_values(array_map(static fn (mixed $p): string => (string) $p, $permissions)) : null,
            expiresAt: $expiresAt instanceof CarbonInterface ? $expiresAt : null,
            meta: is_array($meta) ? $meta : null,
        );
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
