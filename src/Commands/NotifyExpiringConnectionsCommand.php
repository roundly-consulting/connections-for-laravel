<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Actions\Concerns\DispatchesConnectionEvents;
use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Events\ConnectionExpiring;
use RoundlyConsulting\Connections\Models\Connection;

final class NotifyExpiringConnectionsCommand extends Command
{
    use DispatchesConnectionEvents;
    use ResolvesConnections;

    protected $signature = 'connections:notify-expiring {--days=7 : Notify about connections expiring within this many days}';

    protected $description = 'Dispatch a ConnectionExpiring event for each connection expiring soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $count = 0;

        $this->query()
            ->expiringSoon($days)
            ->each(function (Connection $connection) use (&$count): void {
                $this->dispatch(new ConnectionExpiring(
                    $connection,
                    $this->daysUntil($connection),
                ));

                $count++;
            });

        $this->info("Notified {$count} expiring connection(s).");

        return self::SUCCESS;
    }

    private function daysUntil(Connection $connection): int
    {
        $expiresAt = $connection->expires_at;

        if ($expiresAt === null) {
            return 0;
        }

        return (int) ceil(Carbon::now()->diffInDays($expiresAt, false));
    }
}
