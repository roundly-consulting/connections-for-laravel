<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

/**
 * In-request connection cache. Keeps resolved connections in memory for the
 * lifetime of a single request so repeated permission checks don't re-query.
 */
final class Cache
{
    /** @var array<string, mixed> */
    protected static array $cache = [];

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
