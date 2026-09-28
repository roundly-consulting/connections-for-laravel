<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use RoundlyConsulting\Connections\Contracts\Connectable;

/**
 * @internal flush it through `Connections::flushCache()`.
 *
 * In-request connection cache. Keeps resolved connections in memory for the
 * lifetime of a single request so repeated permission checks don't re-query.
 *
 * This cache is request-scoped and not shared across processes — it mirrors a
 * "resolved-this-request" cache, not a persistent store.
 */
final class Cache
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

    public static function enabled(): bool
    {
        return (bool) config('connections.cache.enabled', true);
    }

    public static function keyFor(Connectable $connector, Connectable $connectable): string
    {
        return implode('#', [
            'Connector',
            $connector->getMorphClass(),
            (string) $connector->getKey(),
            'Connectable',
            $connectable->getMorphClass(),
            (string) $connectable->getKey(),
        ]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function forget(string $key): void
    {
        unset(self::$cache[$key]);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$cache);
    }

    public static function get(string $key): mixed
    {
        return self::$cache[$key] ?? null;
    }

    public static function put(string $key, mixed $value): mixed
    {
        return self::$cache[$key] = $value;
    }
}
