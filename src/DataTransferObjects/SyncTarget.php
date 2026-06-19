<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\DataTransferObjects;

use Carbon\CarbonInterface;
use RoundlyConsulting\Connections\Contracts\Connectable;

/**
 * One desired connectable in a syncConnections() reconcile, with the optional
 * attributes it should carry.
 */
final readonly class SyncTarget
{
    /**
     * @param  list<string>|null  $permissions
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public Connectable $model,
        public ?array $permissions = null,
        public ?CarbonInterface $expiresAt = null,
        public ?array $meta = null,
    ) {}
}
