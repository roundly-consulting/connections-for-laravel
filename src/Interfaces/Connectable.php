<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Interfaces;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Connections\Connection;

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

    /** @return mixed */
    public function getKey();

    /** @return string */
    public function getMorphClass();
}
