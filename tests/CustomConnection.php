<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

use RoundlyConsulting\Connections\Models\Connection;

class CustomConnection extends Connection
{
    protected $table = 'connections';
}
