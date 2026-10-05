<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests\Fixtures;

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Connections\Models\Connection;

/**
 * A host subclass of the connection model that lives on a database connection other than the
 * app default — the documented model swap pointed at a second database.
 *
 * {@see self::install()} builds a fresh in-memory SQLite `secondary` connection, runs the
 * package migration on it and points `connections.model` here. Every query an action runs
 * then lands on `secondary`, so a transaction opened on the default connection wraps none of
 * them — which is exactly what the regression tests using this fixture pin.
 */
class SecondaryConnection extends Connection
{
    public const string NAME = 'secondary';

    protected $connection = self::NAME;

    protected $table = 'connections';

    public static function install(): void
    {
        config()->set('database.connections.'.self::NAME, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge(self::NAME);

        $migration = require __DIR__.'/../../database/migrations/create_connections_table.php';
        $default = DB::getDefaultConnection();

        DB::setDefaultConnection(self::NAME);

        try {
            $migration->up();
        } finally {
            DB::setDefaultConnection($default);
        }

        config()->set('connections.model', self::class);
    }
}
