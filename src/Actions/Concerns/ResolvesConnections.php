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

    /**
     * The pair's rows (live only, until a caller widens it).
     *
     * @return Builder<Connection>
     */
    protected function pair(Connectable $connector, Connectable $connectable): Builder
    {
        return $this->query()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass());
    }

    protected function find(Connectable $connector, Connectable $connectable): ?Connection
    {
        /** @var Connection|null $connection */
        $connection = $this->pair($connector, $connectable)->first();

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
        $connection = $this->pair($connector, $connectable)->onlyTrashed()->first();

        return $connection;
    }

    /**
     * The pair's single row — live or soft-deleted, since a trashed row still
     * holds the unique morph index — locked for the rest of the transaction.
     */
    protected function findAnyForUpdate(Connectable $connector, Connectable $connectable): ?Connection
    {
        /** @var Connection|null $connection */
        $connection = $this->pair($connector, $connectable)->withTrashed()->lockForUpdate()->first();

        return $connection;
    }

    /**
     * Like findAnyForUpdate(), but locks only a row that exists: a plain read
     * probes first. On InnoDB a locking read that matches nothing takes a gap
     * lock, gap locks never conflict with each other, and two writers holding
     * one each then deadlock on the inserts that follow (MySQL 1213) instead
     * of one losing on the unique index.
     */
    protected function lockExisting(Connectable $connector, Connectable $connectable): ?Connection
    {
        if (! $this->pair($connector, $connectable)->withTrashed()->exists()) {
            return null;
        }

        return $this->findAnyForUpdate($connector, $connectable);
    }

    /**
     * The pair's live row locked for the rest of the transaction, or null when
     * it has none — probing without a lock first, for the reason
     * lockExisting() gives.
     */
    protected function lockLive(Connectable $connector, Connectable $connectable): ?Connection
    {
        if (! $this->pair($connector, $connectable)->exists()) {
            return null;
        }

        /** @var Connection|null $connection */
        $connection = $this->pair($connector, $connectable)->lockForUpdate()->first();

        return $connection;
    }

    protected function invalidateCache(Connectable $connector, Connectable $connectable): void
    {
        Cache::forget(Cache::keyFor($connector, $connectable));
    }
}
