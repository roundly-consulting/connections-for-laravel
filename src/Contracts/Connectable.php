<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Models\Connection;

interface Connectable
{
    /** @return MorphMany<Connection, covariant Model> */
    public function connections(): MorphMany;

    /** @return MorphMany<Connection, covariant Model> */
    public function connectors(): MorphMany;

    public function isConnectedTo(Connectable $connectable): bool;

    public function isConnectedToAny(string $type): bool;

    public function hasConnector(Connectable $connector): bool;

    public function hasConnectorFromAny(string $type): bool;

    public function hasPermissionThroughConnection(Connectable $connectable, string $permission, bool $force = false): bool;

    public function hasAnyPermissionThroughConnection(Connectable $connectable, string ...$permissions): bool;

    public function hasAllPermissionsThroughConnection(Connectable $connectable, string ...$permissions): bool;

    /**
     * @param  Collection<int, string>|list<string>|null  $permissions
     * @param  array<string, mixed>|null  $meta
     */
    public function connectTo(Connectable $connectable, Collection|array|null $permissions = null, ?CarbonInterface $expiresAt = null, ?array $meta = null): Connection;

    public function disconnectFrom(Connectable $connectable): void;

    /**
     * @param  Collection<int, string>|list<string>|null  $permissions
     */
    public function toggleConnection(Connectable $connectable, Collection|array|null $permissions = null): ?Connection;

    public function reconnectTo(Connectable $connectable): Connection;

    public function inviteConnection(Connectable $connectable): Connection;

    public function acceptConnectionFrom(Connectable $connector): Connection;

    public function blockConnectionFrom(Connectable $connector): Connection;

    public function grantThroughConnection(Connectable $connectable, string ...$permissions): Connection;

    public function revokeThroughConnection(Connectable $connectable, string ...$permissions): Connection;

    public function clearConnectionPermissions(Connectable $connectable): Connection;

    /**
     * @param  Collection<int, string>|list<string>  $permissions
     */
    public function syncConnectionPermissions(Connectable $connectable, Collection|array $permissions): Connection;

    /**
     * @param  iterable<int|string, Connectable|SyncTarget>  $connectables
     */
    public function syncConnections(iterable $connectables): SyncResult;

    /** @return Collection<int, string> */
    public function permissionsThroughConnection(Connectable $connectable): Collection;

    /** @return MorphMany<Connection, covariant Model> */
    public function activeConnections(): MorphMany;

    /** @return MorphMany<Connection, covariant Model> */
    public function expiredConnections(): MorphMany;

    /** @return MorphMany<Connection, covariant Model> */
    public function expiringConnections(int $days = 7): MorphMany;

    /** @return MorphMany<Connection, covariant Model> */
    public function connectionsWithPermission(string $permission): MorphMany;

    /** @return Collection<int, Model> */
    public function connectablesOfType(string $class): Collection;

    /** @return Collection<int, Model> */
    public function connectorsOfType(string $class): Collection;

    /** @return mixed */
    public function getKey();

    /** @return string */
    public function getMorphClass();
}
