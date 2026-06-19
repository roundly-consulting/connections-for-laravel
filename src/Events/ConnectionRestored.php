<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Events;

use RoundlyConsulting\Connections\Models\Connection;

final readonly class ConnectionRestored
{
    public function __construct(
        public Connection $connection,
    ) {}
}
