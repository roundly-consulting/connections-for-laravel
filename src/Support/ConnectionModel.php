<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Support;

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing connections from `connections.model`.
 *
 * The toolkit's ModelResolver returns the packaged {@see Connection} when the key
 * is absent and otherwise requires a Connection or a subclass of it; anything else
 * (a typo, a non-model, an unrelated model that could not answer the package's
 * queries) throws its InvalidConfigurationException naming the key.
 */
final class ConnectionModel
{
    /**
     * @return class-string<Connection>
     */
    public static function class(): string
    {
        return ModelResolver::for('connections.model', Connection::class);
    }

    /**
     * The table the configured model reads from.
     */
    public static function table(): string
    {
        $model = self::class();

        return (new $model)->getTable();
    }
}
