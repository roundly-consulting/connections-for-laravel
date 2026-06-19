<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\PendingConnection;
use RoundlyConsulting\Connections\Testing\ConnectionFake;

/**
 * @method static PendingConnection between(Connectable $connector, Connectable $connectable)
 * @method static PendingConnection from(Connectable $connector)
 * @method static int prune()
 *
 * @see ConnectionManager
 */
final class Connections extends Facade
{
    /**
     * Swap the connection manager for a recording fake (pass-through: real
     * queries still run) and return it for assertions.
     */
    public static function fake(): ConnectionFake
    {
        $fake = new ConnectionFake;

        self::swap($fake);
        self::getFacadeApplication()->instance(ConnectionManager::class, $fake);
        self::getFacadeApplication()->instance('connections', $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'connections';
    }
}
