<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Connection;
use RoundlyConsulting\Connections\Interfaces\Connectable;

final class CreateConnection
{
    /**
     * @param  Collection<int, string>|null  $permissions
     */
    public function execute(
        Model&Connectable $connector,
        Model&Connectable $connectable,
        ?Collection $permissions = null,
        ?Carbon $expiresAt = null,
    ): Connection {
        /** @var Connection $connection */
        $connection = $connector->connections()
            ->updateOrCreate([
                'connectable_id' => $connectable->getKey(),
                'connectable_type' => $connectable->getMorphClass(),
            ], [
                'permissions' => $permissions ?? collect(),
                'expires_at' => $expiresAt,
            ]);

        return $connection;
    }
}
