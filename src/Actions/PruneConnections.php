<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use RoundlyConsulting\Connections\Actions\Concerns\ResolvesConnections;

final readonly class PruneConnections
{
    use ResolvesConnections;

    /**
     * Soft-delete every expired connection and return how many were removed.
     */
    public function execute(): int
    {
        return $this->query()
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
