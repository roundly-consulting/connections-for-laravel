<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Exceptions\ConnectionNotFound;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Support\ConnectionModel;

trait ResolvesConnections
{
    /** @return class-string<Connection> */
    protected function connectionModel(): string
    {
        return ConnectionModel::class();
    }

    /** @return Builder<Connection> */
    protected function query(): Builder
    {
        return $this->connectionModel()::query();
    }

    /**
     * The database the configured model lives on. Every transaction around connection
     * rows opens here: on the app default it would wrap none of them once a host points
     * the model at another connection.
     */
    protected function database(): DatabaseConnection
    {
        return $this->query()->getModel()->getConnection();
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

    /**
     * The pair's single row — live or soft-deleted, since a trashed row still
     * holds the unique morph index — locked for the rest of the transaction.
     */
    protected function findAnyForUpdate(Connectable $connector, Connectable $connectable): ?Connection
    {
        /** @var Connection|null $connection */
        $connection = $this->query()
            ->withTrashed()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass())
            ->lockForUpdate()
            ->first();

        return $connection;
    }

    protected function invalidateCache(Connectable $connector, Connectable $connectable): void
    {
        Cache::forget(Cache::keyFor($connector, $connectable));
    }
}
