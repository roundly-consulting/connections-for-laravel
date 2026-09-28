<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Testing;

use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Connections\ConnectionPermissions;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Models\Connection;

/**
 * The `permissions()` sub-accessor under `Connections::fake()`: writes pass
 * through to the real actions and are recorded once they succeed. `clear()`
 * records as a permission sync to the empty set.
 */
final readonly class RecordingConnectionPermissions extends ConnectionPermissions
{
    public function __construct(
        private ConnectionFake $fake,
        Container $container,
        Connectable $connector,
        Connectable $connectable,
    ) {
        parent::__construct($container, $connector, $connectable);
    }

    public function grant(string ...$permissions): Connection
    {
        return $this->recorded('grant', parent::grant(...$permissions), $permissions);
    }

    public function revoke(string ...$permissions): Connection
    {
        return $this->recorded('revoke', parent::revoke(...$permissions), $permissions);
    }

    public function sync(string ...$permissions): Connection
    {
        return $this->recorded('syncPermissions', parent::sync(...$permissions), $permissions);
    }

    public function clear(): Connection
    {
        return $this->recorded('syncPermissions', parent::clear(), []);
    }

    /**
     * @param  array<int|string, string>  $permissions
     */
    private function recorded(string $verb, Connection $connection, array $permissions): Connection
    {
        $this->fake->record(new RecordedOperation($verb, $this->connector, $this->connectable, array_values($permissions)));

        return $connection;
    }
}
