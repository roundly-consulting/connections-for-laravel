<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Actions\AcceptConnection;
use RoundlyConsulting\Connections\Actions\BlockConnection;
use RoundlyConsulting\Connections\Actions\BulkConnect;
use RoundlyConsulting\Connections\Actions\BulkDisconnect;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\DisconnectConnection;
use RoundlyConsulting\Connections\Actions\ExtendConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Actions\RestoreConnection;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Actions\SyncConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Exceptions\MissingConnectable;
use RoundlyConsulting\Connections\Models\Connection;

/**
 * Fluent builder for connection operations between a connector and a
 * (possibly deferred) connectable. Every terminal verb resolves its action from
 * the container, so host overrides and `Connections::fake()` apply.
 *
 * Not final: the recording fake extends it.
 */
class PendingConnection
{
    /** @var list<string>|null */
    protected ?array $permissions = null;

    protected ?CarbonInterface $expiresAt = null;

    protected ?ConnectionStatus $status = null;

    /** @var array<string, mixed>|null */
    protected ?array $meta = null;

    protected bool $replaceMeta = false;

    /** @var list<Connectable> */
    protected array $connectables = [];

    public function __construct(
        protected readonly Container $container,
        protected readonly Connectable $connector,
        protected ?Connectable $connectable = null,
    ) {}

    public function to(Connectable $connectable): self
    {
        $this->connectable = $connectable;

        return $this;
    }

    /**
     * Stage a collection of connectables for a bulk operation.
     *
     * @param  iterable<int, Connectable>  $connectables
     */
    public function toMany(iterable $connectables): self
    {
        $staged = [];

        foreach ($connectables as $connectable) {
            $staged[] = $connectable;
        }

        $this->connectables = $staged;

        return $this;
    }

    public function withPermissions(string ...$permissions): self
    {
        $this->permissions = array_values(array_unique([...$this->permissions ?? [], ...array_values($permissions)]));

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

    /**
     * Merge the given values into the staged meta bag.
     *
     * @param  array<string, mixed>  $meta
     */
    public function withMeta(array $meta): self
    {
        $this->meta = [...$this->meta ?? [], ...$meta];

        return $this;
    }

    /**
     * On re-connect, replace the stored meta with the staged meta instead of
     * shallow-merging into it.
     */
    public function replaceMeta(): self
    {
        $this->replaceMeta = true;

        return $this;
    }

    /**
     * Stage the connection to be created in the pending state.
     */
    public function asPending(): self
    {
        $this->status = ConnectionStatus::Pending;

        return $this;
    }

    public function connect(): Connection
    {
        return $this->container->make(CreateConnection::class)->execute(
            $this->connector,
            $this->resolveConnectable(),
            $this->permissionCollection(),
            $this->expiresAt,
            $this->status,
            $this->meta,
            $this->replaceMeta,
        );
    }

    /**
     * Sugar for asPending()->connect().
     */
    public function invite(): Connection
    {
        return $this->asPending()->connect();
    }

    public function accept(): Connection
    {
        return $this->container->make(AcceptConnection::class)->execute($this->connector, $this->resolveConnectable());
    }

    public function block(): Connection
    {
        return $this->container->make(BlockConnection::class)->execute($this->connector, $this->resolveConnectable());
    }

    public function disconnect(): void
    {
        $this->container->make(DisconnectConnection::class)->execute($this->connector, $this->resolveConnectable());
    }

    /**
     * Disconnect an active connection if one exists (returning null), otherwise
     * connect (returning the new Connection).
     */
    public function toggle(): ?Connection
    {
        $connectable = $this->resolveConnectable();

        if ($this->connector->isConnectedTo($connectable)) {
            $this->disconnect();

            return null;
        }

        return $this->connect();
    }

    /**
     * Restore a soft-deleted connection for the pair, or connect afresh when
     * nothing is trashed.
     */
    public function reconnect(): Connection
    {
        return $this->container->make(RestoreConnection::class)->execute($this->connector, $this->resolveConnectable());
    }

    /**
     * Alias of reconnect().
     */
    public function restore(): Connection
    {
        return $this->reconnect();
    }

    public function extend(?CarbonInterface $expiresAt): Connection
    {
        return $this->container->make(ExtendConnection::class)->execute($this->connector, $this->resolveConnectable(), $expiresAt);
    }

    /**
     * Whether an active connection exists for the pair (any stored connection
     * when `enforce_active_on_check` is off).
     */
    public function exists(): bool
    {
        return $this->connector->isConnectedTo($this->resolveConnectable());
    }

    /**
     * The pair's connection in any status, or null when there is none (or it
     * is soft-deleted).
     */
    public function find(): ?Connection
    {
        $connectable = $this->resolveConnectable();

        /** @var Connection|null $connection */
        $connection = $this->connector->connections()
            ->where('connectable_id', $connectable->getKey())
            ->where('connectable_type', $connectable->getMorphClass())
            ->first();

        return $connection;
    }

    /**
     * The permission set on this pair: grant / revoke / sync / clear and the
     * all / has / hasAny / hasAll checks.
     */
    public function permissions(): ConnectionPermissions
    {
        return new ConnectionPermissions($this->container, $this->connector, $this->resolveConnectable());
    }

    /**
     * Reconcile the connector's connections to exactly the given set: connect
     * what is missing, disconnect the extras and update the overlap. Uses the
     * connector only — a staged connectable is ignored.
     *
     * @param  iterable<int|string, Connectable|SyncTarget>  $connectables
     */
    public function sync(iterable $connectables): SyncResult
    {
        $targets = [];

        foreach ($connectables as $connectable) {
            $targets[] = $connectable instanceof SyncTarget ? $connectable : new SyncTarget($connectable);
        }

        return $this->container->make(SyncConnections::class)->execute($this->connector, $targets);
    }

    /**
     * Connect the connector to every staged connectable.
     *
     * @return Collection<int, Connection>
     */
    public function connectAll(): Collection
    {
        return $this->container->make(BulkConnect::class)->execute(
            $this->connector,
            $this->resolveConnectables(),
            $this->permissionCollection(),
            $this->expiresAt,
            $this->status,
            $this->meta,
        );
    }

    /**
     * Disconnect the connector from every staged connectable.
     */
    public function disconnectAll(): void
    {
        $this->container->make(BulkDisconnect::class)->execute($this->connector, $this->resolveConnectables());
    }

    /**
     * Grant the given permissions on every staged connectable.
     *
     * @return Collection<int, Connection>
     */
    public function grantAll(string ...$permissions): Collection
    {
        $permissions = $permissions === [] ? $this->permissions ?? [] : array_values($permissions);

        $action = $this->container->make(GrantPermissions::class);

        $results = new Collection;

        foreach ($this->resolveConnectables() as $connectable) {
            $results->push($action->execute($this->connector, $connectable, ...$permissions));
        }

        return $results;
    }

    /**
     * Revoke the given permissions on every staged connectable that exists.
     *
     * @return Collection<int, Connection>
     */
    public function revokeAll(string ...$permissions): Collection
    {
        $action = $this->container->make(RevokePermissions::class);

        $results = new Collection;

        foreach ($this->resolveConnectables() as $connectable) {
            if (! $this->connector->isConnectedTo($connectable)) {
                continue;
            }

            $results->push($action->execute($this->connector, $connectable, ...array_values($permissions)));
        }

        return $results;
    }

    /**
     * @return Collection<int, string>|null
     */
    private function permissionCollection(): ?Collection
    {
        return $this->permissions === null ? null : new Collection($this->permissions);
    }

    protected function resolveConnectable(): Connectable
    {
        return $this->connectable ?? throw MissingConnectable::make();
    }

    /**
     * @return list<Connectable>
     */
    private function resolveConnectables(): array
    {
        return $this->connectables === [] ? throw MissingConnectable::collection() : $this->connectables;
    }
}
