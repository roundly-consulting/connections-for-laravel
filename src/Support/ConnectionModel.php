<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Support;

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing connections from `connections.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model. This package's documented contract is that a misconfigured
 * value never takes an app down — anything that is not a Connection (so it
 * could not answer the package's queries) falls back to the packaged model.
 */
final class ConnectionModel
{
    /**
     * @return class-string<Connection>
     */
    public static function class(): string
    {
        try {
            $model = ModelResolver::for('connections.model', Connection::class);
        } catch (InvalidConfigurationException) {
            return Connection::class;
        }

        return is_a($model, Connection::class, true) ? $model : Connection::class;
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
