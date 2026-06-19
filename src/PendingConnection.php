<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\DisconnectConnection;
use RoundlyConsulting\Connections\Actions\ExtendConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Actions\SyncPermissions;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Exceptions\MissingConnectable;
use RoundlyConsulting\Connections\Models\Connection;

/**
 * Fluent builder for connection operations between a connector and a
 * (possibly deferred) connectable.
 */
final class PendingConnection
{
    /** @var list<string> */
    private array $permissions = [];

    private ?CarbonInterface $expiresAt = null;

    public function __construct(
        private readonly Connectable $connector,
        private ?Connectable $connectable = null,
    ) {}

    public function to(Connectable $connectable): self
    {
        $this->connectable = $connectable;

        return $this;
    }

    public function withPermissions(string ...$permissions): self
    {
        $this->permissions = array_values(array_unique([...$this->permissions, ...array_values($permissions)]));

        return $this;
    }

    public function expiringAt(?CarbonInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function expiresIn(CarbonInterval|CarbonInterface|int $value): self
    {
        $this->expiresAt = match (true) {
            $value instanceof CarbonInterface => $value,
            $value instanceof CarbonInterval => Carbon::now()->add($value),
            default => Carbon::now()->addSeconds($value),
        };

        return $this;
    }

    public function connect(): Connection
    {
        return app(CreateConnection::class)->execute(
            $this->connector,
            $this->resolveConnectable(),
            new Collection($this->permissions),
            $this->expiresAt,
        );
    }

    public function disconnect(): void
    {
        app(DisconnectConnection::class)->execute($this->connector, $this->resolveConnectable());
    }

    public function grant(string ...$permissions): Connection
    {
        $permissions = $permissions === [] ? $this->permissions : array_values($permissions);

        return app(GrantPermissions::class)->execute($this->connector, $this->resolveConnectable(), ...$permissions);
    }

    public function revoke(string ...$permissions): Connection
    {
        return app(RevokePermissions::class)->execute($this->connector, $this->resolveConnectable(), ...array_values($permissions));
    }

    public function sync(string ...$permissions): Connection
    {
        $permissions = $permissions === [] ? $this->permissions : array_values($permissions);

        return app(SyncPermissions::class)->execute($this->connector, $this->resolveConnectable(), ...$permissions);
    }

    public function extend(?CarbonInterface $expiresAt): Connection
    {
        return app(ExtendConnection::class)->execute($this->connector, $this->resolveConnectable(), $expiresAt);
    }

    public function exists(): bool
    {
        return $this->connector->isConnectedTo($this->resolveConnectable());
    }

    public function can(string $permission): bool
    {
        return $this->connector->hasPermissionThroughConnection($this->resolveConnectable(), $permission);
    }

    private function resolveConnectable(): Connectable
    {
        return $this->connectable ?? throw MissingConnectable::make();
    }
}
