<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Events;

use RoundlyConsulting\Connections\Models\Connection;

final class ConnectionPermissionsChanged
{
    /**
     * @param  list<string>  $previous
     * @param  list<string>  $current
     */
    public function __construct(
        public readonly Connection $connection,
        public readonly array $previous,
        public readonly array $current,
    ) {}
}
