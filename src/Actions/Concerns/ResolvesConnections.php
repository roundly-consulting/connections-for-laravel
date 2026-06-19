<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;

trait ResolvesConnections
{
    /** @return class-string<Connection> */
    protected function connectionModel(): string
    {
        $model = config('connections.model', Connection::class);

        if (is_string($model) && is_a($model, Connection::class, true)) {
            return $model;
        }

        return Connection::class;
    }

    /** @return Builder<Connection> */
    protected function query(): Builder
    {
        return $this->connectionModel()::query();
    }

    protected function find(Connectable $connector, Connectable $connectable): ?Connection
    {
        /** @var Connection|null $connection */
        $connection = $this->query()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass())
            ->first();

        return $connection;
    }

    protected function findOrFail(Connectable $connector, Connectable $connectable): Connection
    {
        return $this->find($connector, $connectable)
            ?? throw ConnectionNotFound::between($connector, $connectable);
    }

    protected function findTrashed(Connectable $connector, Connectable $connectable): ?Connection
    {
        /** @var Connection|null $connection */
        $connection = $this->query()
            ->onlyTrashed()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass())
            ->first();

        return $connection;
    }

    protected function invalidateCache(Connectable $connector, Connectable $connectable): void
    {
        Cache::forget(Cache::keyFor($connector, $connectable));
    }
}
