<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Facades;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\PendingConnection;
use RoundlyConsulting\Connections\Testing\ConnectionFake;

/**
 * @method static PendingConnection between(Connectable $connector, Connectable $connectable)
 * @method static PendingConnection from(Connectable $connector)
 * @method static Builder<Connection> expiring(int $days = 7)
 * @method static int prune()
 * @method static void flushCache()
 *
 * @see ConnectionManager
 */
final class Connections extends Facade
{
    /**
     * Swap the connection manager for a recording fake (pass-through: real
     * queries still run) and return it for assertions. The container binding
     * is swapped too, so injected managers and model traits record as well.
     */
    public static function fake(): ConnectionFake
    {
        $fake = self::getFacadeApplication()->make(ConnectionFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ConnectionManager::class;
    }
}
