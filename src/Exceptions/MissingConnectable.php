<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Exceptions;

final class MissingConnectable extends ConnectionsException
{
    public static function make(): self
    {
        return new self('No connectable model was provided. Call to() or between() before a terminal verb.');
    }
}
