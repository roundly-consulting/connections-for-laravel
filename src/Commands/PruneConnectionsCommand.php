<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Connections\ConnectionManager;

final class PruneConnectionsCommand extends Command
{
    protected $signature = 'connections:prune';

    protected $description = 'Soft-delete expired connections';

    public function handle(ConnectionManager $connections): int
    {
        $count = $connections->prune();

        $this->info("Pruned {$count} expired connection(s).");

        return self::SUCCESS;
    }
}
