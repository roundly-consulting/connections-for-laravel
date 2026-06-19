<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Exceptions;

use RoundlyConsulting\Connections\Contracts\Connectable;

final class ConnectionNotFound extends ConnectionsException
{
    public static function between(Connectable $connector, Connectable $connectable): self
    {
        return new self(sprintf(
            'No connection exists between [%s:%s] and [%s:%s].',
            $connector->getMorphClass(),
            (string) $connector->getKey(),
            $connectable->getMorphClass(),
            (string) $connectable->getKey(),
        ));
    }
}
