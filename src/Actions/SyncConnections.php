<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Cache;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class SyncConnections
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    public function __construct(
        private readonly CreateConnection $createConnection,
    ) {}

    /**
     * Reconcile the connector's connections to exactly the given set: connect
     * any missing, disconnect any extras, and update attributes on overlap.
     * Runs in a transaction so a failing item rolls the whole reconcile back.
     *
     * @param  list<SyncTarget>  $targets
     */
    public function execute(Connectable $connector, array $targets): SyncResult
    {
        /** @var SyncResult $result */
        $result = DB::transaction(function () use ($connector, $targets): SyncResult {
            /** @var list<int|string> $attached */
            $attached = [];
            /** @var list<int|string> $updated */
            $updated = [];

            $desired = $this->index($targets);

            foreach ($targets as $target) {
                $connectable = $target->model;
                $existed = $this->find($connector, $connectable) !== null;

                $this->createConnection->execute(
                    $connector,
                    $connectable,
                    $target->permissions === null ? null : new Collection($target->permissions),
                    $target->expiresAt,
                    null,
                    $target->meta,
                );

                $key = $this->scalarKey($connectable->getKey());

                $existed
                    ? $updated[] = $key
                    : $attached[] = $key;
            }

            $detached = $this->detachExtras($connector, $desired);

            return new SyncResult(
                attached: $attached,
                detached: $detached,
                updated: $updated,
            );
        });

        return $result;
    }

    /**
     * @param  list<SyncTarget>  $targets
     * @return array<string, true>
     */
    private function index(array $targets): array
    {
        $index = [];

        foreach ($targets as $target) {
            $index[$this->keyFor($target->model)] = true;
        }

        return $index;
    }

    /**
     * @param  array<string, true>  $desired
     * @return list<int|string>
     */
    private function detachExtras(Connectable $connector, array $desired): array
    {
        /** @var list<int|string> $detached */
        $detached = [];

        /** @var Collection<int, Connection> $current */
        $current = $this->query()
            ->where('connector_id', $connector->getKey())
            ->where('connector_type', $connector->getMorphClass())
            ->get();

        foreach ($current as $connection) {
            $key = $connection->connectable_type.'#'.$connection->connectable_id;

            if (isset($desired[$key])) {
                continue;
            }

            $connection->delete();
            $this->invalidateCacheByConnection($connector, $connection);
            $this->dispatch(new ConnectionRemoved($connection));
            $detached[] = $connection->connectable_id;
        }

        return $detached;
    }

    private function keyFor(Connectable $connectable): string
    {
        return $connectable->getMorphClass().'#'.$this->scalarKey($connectable->getKey());
    }

    private function scalarKey(mixed $key): int|string
    {
        return is_int($key) ? $key : (string) $key;
    }

    private function invalidateCacheByConnection(Connectable $connector, Connection $connection): void
    {
        Cache::forget(implode('#', [
            'Connector',
            $connector->getMorphClass(),
            (string) $connector->getKey(),
            'Connectable',
            $connection->connectable_type,
            (string) $connection->connectable_id,
        ]));
    }
}
