<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Illuminate\Container\Container;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * @internal flush it through `Connections::flushCache()`.
 *
 * In-request connection cache. Keeps resolved connections in memory so
 * repeated permission checks within one request or job don't re-query.
 *
 * The entries live on a container-**scoped** instance (bound by the service
 * provider), never in process-wide static state: Laravel drops scoped
 * instances whenever it starts a new lifecycle — each Octane request and each
 * queue-worker job — and a fresh application never sees another's entries. A
 * permission revoked elsewhere therefore stops authorizing on the next request
 * or job at the latest.
 */
final class Cache
{
    /** @var array<string, mixed> */
    private array $entries = [];

    public static function enabled(): bool
    {
        return Config::boolean('connections.cache.enabled', true);
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
        self::store()->entries = [];
    }

    public static function forget(string $key): void
    {
        unset(self::store()->entries[$key]);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::store()->entries);
    }

    public static function get(string $key): mixed
    {
        return self::store()->entries[$key] ?? null;
    }

    public static function put(string $key, mixed $value): mixed
    {
        return self::store()->entries[$key] = $value;
    }

    /**
     * The current lifecycle's instance. The service provider binds it scoped;
     * should it be missing (provider not registered), bind it the same way.
     */
    private static function store(): self
    {
        $container = Container::getInstance();

        if (! $container->bound(self::class)) {
            $container->scoped(self::class);
        }

        /** @var self $store */
        $store = $container->make(self::class);

        return $store;
    }
}
