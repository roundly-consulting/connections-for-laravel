<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Enums;

/**
 * The lifecycle state of a connection.
 *
 * A connection is created in one of these states (default per config) and may
 * move between them via the Accept/Block actions. Only an accepted, unexpired
 * connection is considered "active" for access checks.
 */
enum ConnectionStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Blocked = 'blocked';

    /**
     * Whether a transition from this state to the target is permitted.
     *
     * The guard is intentionally lenient: accepting is allowed from pending or
     * blocked (re-accepting a blocked link un-blocks it), blocking is allowed
     * from any state, and a transition to the current state is always a no-op.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($target) {
            self::Accepted => $this === self::Pending || $this === self::Blocked,
            self::Blocked => true,
            self::Pending => false,
        };
    }
}
