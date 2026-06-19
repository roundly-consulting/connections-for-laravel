<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Testing;

use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\PendingConnection;

/**
 * A PendingConnection that records terminal verbs against the fake while still
 * passing them through to the real builder behaviour (so DB reads/writes work).
 */
final class RecordingPendingConnection extends PendingConnection
{
    private ConnectionFake $fake;

    public function __construct(
        ConnectionFake $fake,
        Connectable $connector,
        ?Connectable $connectable = null,
    ) {
        parent::__construct($connector, $connectable);

        $this->fake = $fake;
    }

    public function connect(): Connection
    {
        $this->fake->record('connect', $this->connector, $this->connectable, $this->permissions ?? []);

        return parent::connect();
    }

    public function invite(): Connection
    {
        $this->fake->record('invite', $this->connector, $this->connectable, $this->permissions ?? []);

        // Stage pending then call the parent connect directly so we don't
        // double-record via the overridden connect().
        $this->asPending();

        return parent::connect();
    }

    public function accept(): Connection
    {
        $this->fake->record('accept', $this->connector, $this->connectable, $this->permissions ?? []);

        return parent::accept();
    }

    public function block(): Connection
    {
        $this->fake->record('block', $this->connector, $this->connectable, $this->permissions ?? []);

        return parent::block();
    }

    public function disconnect(): void
    {
        $this->fake->record('disconnect', $this->connector, $this->connectable, $this->permissions ?? []);

        parent::disconnect();
    }

    public function grant(string ...$permissions): Connection
    {
        $this->fake->record('grant', $this->connector, $this->connectable, array_values($permissions));

        return parent::grant(...$permissions);
    }

    public function revoke(string ...$permissions): Connection
    {
        $this->fake->record('revoke', $this->connector, $this->connectable, array_values($permissions));

        return parent::revoke(...$permissions);
    }
}
