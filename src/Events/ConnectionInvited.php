<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Events;

use RoundlyConsulting\Connections\Models\Connection;

final readonly class ConnectionInvited
{
    public function __construct(
        public Connection $connection,
    ) {}
}
