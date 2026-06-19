<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Contracts\Connectable;

final readonly class ConnectionData
{
    public function __construct(
        public string $connectorType,
        public int|string $connectorId,
        public string $connectableType,
        public int|string $connectableId,
        public PermissionSet $permissions = new PermissionSet,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    /**
     * @param  Collection<int, string>|list<string>|PermissionSet|null  $permissions
     */
    public static function fromModels(
        Connectable $connector,
        Connectable $connectable,
        Collection|array|PermissionSet|null $permissions = null,
        ?CarbonInterface $expiresAt = null,
    ): self {
        return new self(
            connectorType: $connector->getMorphClass(),
            connectorId: $connector->getKey(),
            connectableType: $connectable->getMorphClass(),
            connectableId: $connectable->getKey(),
            permissions: self::normalizePermissions($permissions),
            expiresAt: $expiresAt,
        );
    }

    /**
     * @param  Collection<int, string>|list<string>|PermissionSet|null  $permissions
     */
    private static function normalizePermissions(Collection|array|PermissionSet|null $permissions): PermissionSet
    {
        return match (true) {
            $permissions instanceof PermissionSet => $permissions,
            $permissions instanceof Collection => PermissionSet::fromIterable($permissions->all()),
            is_array($permissions) => new PermissionSet($permissions),
            default => new PermissionSet,
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
