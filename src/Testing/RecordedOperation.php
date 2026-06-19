<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Testing;

use RoundlyConsulting\Connections\Contracts\Connectable;

/**
 * A single connection operation captured by ConnectionFake.
 */
final readonly class RecordedOperation
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $verb,
        public Connectable $connector,
        public ?Connectable $connectable = null,
        public array $permissions = [],
    ) {}

    public function matches(string $verb, Connectable $connector, ?Connectable $connectable): bool
    {
        if ($this->verb !== $verb) {
            return false;
        }

        if (! $this->sameModel($this->connector, $connector)) {
            return false;
        }

        if ($connectable === null) {
            return true;
        }

        return $this->connectable !== null && $this->sameModel($this->connectable, $connectable);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    private function sameModel(Connectable $a, Connectable $b): bool
    {
        return $a->getMorphClass() === $b->getMorphClass()
            && (string) $a->getKey() === (string) $b->getKey();
    }
}
