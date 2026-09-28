<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The lifecycle state of a connection.
 *
 * A connection is created in one of these states (default per config) and may
 * move between them via the Accept/Block actions (see canTransitionTo()). Only
 * an accepted, unexpired connection is considered "active" for access checks.
 *
 * Ships the shared {@see Helpers} trait from enums-for-laravel, adding
 * value/label/option helpers (`values()`, `labels()`, `options()`,
 * `toOptions()`, `validationRule()`, `readable()`, case lookups) on top of the
 * domain-specific transition guard below.
 */
enum ConnectionStatus: string
{
    use Helpers;

    case Pending = 'pending';
    case Accepted = 'accepted';
    case Blocked = 'blocked';

    /**
     * Whether a transition from this state to the target is permitted.
     *
     * Accepting is allowed from pending or blocked (accept() on a blocked link
     * is the explicit unblock), blocking is allowed from any state, nothing
     * moves back to pending, and a transition to the current state is always a
     * no-op. Every action enforces it; the connect-side verbs (connect, invite,
     * toggle, sync) are stricter still and never lift a block.
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
