<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Exceptions;

use RoundlyConsulting\Connections\Enums\ConnectionStatus;

/**
 * A write asked a connection to move to a status its current one does not
 * allow (see ConnectionStatus::canTransitionTo()), or asked a connect-side verb
 * (connect / invite / toggle / sync) to lift a block — only accept() may.
 */
final class InvalidStatusTransition extends ConnectionsException
{
    public static function from(ConnectionStatus $current, ConnectionStatus $target): self
    {
        return new self(sprintf(
            'A connection cannot move from [%s] to [%s].',
            $current->value,
            $target->value,
        ));
    }

    public static function blocked(ConnectionStatus $target): self
    {
        return new self(sprintf(
            'The connection is blocked; only accept() may lift a block, not a move to [%s].',
            $target->value,
        ));
    }
}
