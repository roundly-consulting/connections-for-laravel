<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Connections\Actions\PruneConnections;

final class PruneConnectionsCommand extends Command
{
    protected $signature = 'connections:prune';

    protected $description = 'Soft-delete expired connections';

    public function handle(PruneConnections $pruneConnections): int
    {
        $count = $pruneConnections->execute();

        $this->info("Pruned {$count} expired connection(s).");

        return self::SUCCESS;
    }
}
