<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Models\Connection;

final readonly class BulkConnect
{
    public function __construct(
        private readonly CreateConnection $createConnection,
    ) {}

    /**
     * Connect the connector to every connectable in one transaction. Each item
     * still flows through CreateConnection so events and cache stay correct.
     *
     * @param  list<Connectable>  $connectables
     * @param  Collection<int, string>|null  $permissions
     * @param  array<string, mixed>|null  $meta
     * @return Collection<int, Connection>
     */
    public function execute(
        Connectable $connector,
        array $connectables,
        ?Collection $permissions = null,
        ?CarbonInterface $expiresAt = null,
        ?ConnectionStatus $status = null,
        ?array $meta = null,
    ): Collection {
        /** @var Collection<int, Connection> $created */
        $created = DB::transaction(function () use ($connector, $connectables, $permissions, $expiresAt, $status, $meta): Collection {
            $result = new Collection;

            foreach ($connectables as $connectable) {
                $result->push($this->createConnection->execute(
                    $connector,
                    $connectable,
                    $permissions,
                    $expiresAt,
                    $status,
                    $meta,
                ));
            }

            return $result;
        });

        return $created;
    }
}
