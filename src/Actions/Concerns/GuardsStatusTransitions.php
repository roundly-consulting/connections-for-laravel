<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Exceptions\InvalidStatusTransition;

trait GuardsStatusTransitions
{
    /**
     * Refuse a status change ConnectionStatus::canTransitionTo() does not allow.
     *
     * @throws InvalidStatusTransition
     */
    protected function guardTransition(ConnectionStatus $current, ConnectionStatus $target): void
    {
        if (! $current->canTransitionTo($target)) {
            throw InvalidStatusTransition::from($current, $target);
        }
    }

    /**
     * The connect-side guard (connect / invite / toggle / sync): on top of the
     * state machine, a block is never lifted here — only accept() may.
     *
     * @throws InvalidStatusTransition
     */
    protected function guardConnectTransition(ConnectionStatus $current, ConnectionStatus $target): void
    {
        if ($current === ConnectionStatus::Blocked && $target !== ConnectionStatus::Blocked) {
            throw InvalidStatusTransition::blocked($target);
        }

        $this->guardTransition($current, $target);
    }
}
