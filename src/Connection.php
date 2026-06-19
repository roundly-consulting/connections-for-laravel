<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use RoundlyConsulting\Connections\Models\Connection as BaseConnection;

/*
 * Backward-compatible alias for the connection model, which now lives at
 * RoundlyConsulting\Connections\Models\Connection. Prefer the Models\ FQCN.
 */
class_alias(BaseConnection::class, __NAMESPACE__.'\\Connection');
