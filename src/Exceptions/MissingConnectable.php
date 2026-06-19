<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Exceptions;

final class MissingConnectable extends ConnectionsException
{
    public static function make(): self
    {
        return new self('No connectable model was provided. Call to() or between() before a terminal verb.');
    }

    public static function collection(): self
    {
        return new self('No connectables were provided. Call toMany() with at least one model before a bulk verb.');
    }
}
