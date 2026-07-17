<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests\Fixtures;

use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host subclass `connections.model` invites, used to prove the seam is real.
 *
 * Distinct from the existing `Tests\CustomConnection` fixture, which several tests use to
 * exercise the resolver's *validation* contract at runtime. This one carries
 * `CountsCreations`, which is what makes the swap proof independent of `instanceof`: it
 * counts rows created as *this exact class*, so a connection row created as the packaged
 * Connection — which would still satisfy `instanceof` while firing none of the host's
 * model events (permissions #31) — cannot be mistaken for an honoured swap.
 */
class CountedConnection extends Connection
{
    use CountsCreations;

    protected $table = 'connections';
}
