<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use RoundlyConsulting\Connections\Models\Connection;

/**
 * Dispatched after the surrounding transaction commits, never for a rolled-back write.
 */
final class ConnectionRemoved implements ShouldDispatchAfterCommit
{
    public function __construct(
        public readonly Connection $connection,
    ) {}
}
