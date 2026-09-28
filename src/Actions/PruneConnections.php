<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;
use RoundlyConsulting\Connections\Cache;

final readonly class PruneConnections
{
    use ResolvesConnections;

    /**
     * Soft-delete every expired connection and return how many were removed.
     * A mass delete touches rows across many pairs, so the whole in-request
     * permission cache is dropped rather than left serving pruned rows.
     */
    public function execute(): int
    {
        $pruned = $this->query()
            ->where('expires_at', '<=', now())
            ->delete();

        if ($pruned > 0) {
            Cache::flush();
        }

        return $pruned;
    }
}
