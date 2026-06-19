<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Events;

use RoundlyConsulting\Connections\Models\Connection;

final class ConnectionUpdated
{
    public function __construct(
        public readonly Connection $connection,
    ) {}
}
