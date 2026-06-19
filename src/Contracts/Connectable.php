<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Models\Connection;

interface Connectable
{
    /** @return MorphMany<Connection, covariant \Illuminate\Database\Eloquent\Model> */
    public function connections(): MorphMany;

    /** @return MorphMany<Connection, covariant \Illuminate\Database\Eloquent\Model> */
    public function connectors(): MorphMany;

    public function isConnectedTo(Connectable $connectable): bool;

    public function isConnectedToAny(string $type): bool;

    public function hasConnector(Connectable $connector): bool;

    public function hasConnectorFromAny(string $type): bool;

    public function hasPermissionThroughConnection(Connectable $connectable, string $permission, bool $force = false): bool;

    /**
     * @param  Collection<int, string>|list<string>|null  $permissions
     */
    public function connectTo(Connectable $connectable, Collection|array|null $permissions = null, ?CarbonInterface $expiresAt = null): Connection;

    public function disconnectFrom(Connectable $connectable): void;

    public function grantThroughConnection(Connectable $connectable, string ...$permissions): Connection;

    public function revokeThroughConnection(Connectable $connectable, string ...$permissions): Connection;

    /**
     * @param  Collection<int, string>|list<string>  $permissions
     */
    public function syncConnectionPermissions(Connectable $connectable, Collection|array $permissions): Connection;

    /** @return Collection<int, string> */
    public function permissionsThroughConnection(Connectable $connectable): Collection;

    /** @return mixed */
    public function getKey();

    /** @return string */
    public function getMorphClass();
}
