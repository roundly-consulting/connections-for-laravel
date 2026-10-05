<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;

final readonly class ConnectionData
{
    /**
     * A null $permissions means "the caller supplied none" — the create action
     * then applies config('connections.default_permissions'). An explicit
     * (even empty) PermissionSet is honoured as-is. With $mergePermissions a
     * write that lands on the pair's live row adds them to its stored set
     * instead of replacing it — grant semantics, so a grant that loses the
     * create race to a concurrent writer keeps what that writer stored.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $connectorType,
        public int|string $connectorId,
        public string $connectableType,
        public int|string $connectableId,
        public ?PermissionSet $permissions = null,
        public ?CarbonInterface $expiresAt = null,
        public ?ConnectionStatus $status = null,
        public ?array $meta = null,
        public bool $replaceMeta = false,
        public bool $mergePermissions = false,
    ) {}

    /**
     * @param  Collection<int, string>|list<string>|PermissionSet|null  $permissions
     * @param  array<string, mixed>|null  $meta
     */
    public static function fromModels(
        Connectable $connector,
        Connectable $connectable,
        Collection|array|PermissionSet|null $permissions = null,
        ?CarbonInterface $expiresAt = null,
        ?ConnectionStatus $status = null,
        ?array $meta = null,
        bool $replaceMeta = false,
        bool $mergePermissions = false,
    ): self {
        return new self(
            connectorType: $connector->getMorphClass(),
            connectorId: $connector->getKey(),
            connectableType: $connectable->getMorphClass(),
            connectableId: $connectable->getKey(),
            permissions: self::normalizePermissions($permissions),
            expiresAt: $expiresAt,
            status: $status,
            meta: $meta,
            replaceMeta: $replaceMeta,
            mergePermissions: $mergePermissions,
        );
    }

    /**
     * @param  Collection<int, string>|list<string>|PermissionSet|null  $permissions
     */
    private static function normalizePermissions(Collection|array|PermissionSet|null $permissions): ?PermissionSet
    {
        return match (true) {
            $permissions instanceof PermissionSet => $permissions,
            $permissions instanceof Collection => PermissionSet::fromIterable($permissions->all()),
            is_array($permissions) => new PermissionSet($permissions),
            default => null,
        };
    }

    /** @return array{connectable_id: int|string, connectable_type: string} */
    public function connectableKeys(): array
    {
        return [
            'connectable_id' => $this->connectableId,
            'connectable_type' => $this->connectableType,
        ];
    }

    /** @return array{connector_id: int|string, connector_type: string} */
    public function connectorKeys(): array
    {
        return [
            'connector_id' => $this->connectorId,
            'connector_type' => $this->connectorType,
        ];
    }
}
