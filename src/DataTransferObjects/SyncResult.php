<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\DataTransferObjects;

/**
 * The outcome of a syncConnections() reconcile, mirroring Eloquent's
 * attached/detached/updated shape but typed.
 */
final readonly class SyncResult
{
    /**
     * @param  list<int|string>  $attached
     * @param  list<int|string>  $detached
     * @param  list<int|string>  $updated
     */
    public function __construct(
        public array $attached = [],
        public array $detached = [],
        public array $updated = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->attached === [] && $this->detached === [] && $this->updated === [];
    }
}
