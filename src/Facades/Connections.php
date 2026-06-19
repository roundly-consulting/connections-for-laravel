<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\PendingConnection;

/**
 * @method static PendingConnection between(Connectable $connector, Connectable $connectable)
 * @method static PendingConnection from(Connectable $connector)
 * @method static int prune()
 *
 * @see ConnectionManager
 */
final class Connections extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'connections';
    }
}
