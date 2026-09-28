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
     * @param  list<Connectable>  $targets  the desired set of a `sync` reconcile
     */
    public function __construct(
        public string $verb,
        public ?Connectable $connector = null,
        public ?Connectable $connectable = null,
        public array $permissions = [],
        public array $targets = [],
    ) {}

    /**
     * Whether this operation is `$verb` for the connector (and, when given,
     * the connectable). A null `$connector` matches any connector.
     */
    public function matches(string $verb, ?Connectable $connector = null, ?Connectable $connectable = null): bool
    {
        if ($this->verb !== $verb) {
            return false;
        }

        if ($connector !== null && ($this->connector === null || ! $this->sameModel($this->connector, $connector))) {
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

    /**
     * Whether the recorded `sync` targets are exactly the given models, in any order.
     *
     * @param  list<Connectable>  $connectables
     */
    public function targetsAre(array $connectables): bool
    {
        $keys = static fn (array $models): array => array_values(array_unique(array_map(
            static fn (Connectable $model): string => $model->getMorphClass().'#'.(string) $model->getKey(),
            $models,
        )));

        $expected = $keys($connectables);
        $actual = $keys($this->targets);

        sort($expected);
        sort($actual);

        return $expected === $actual;
    }

    private function sameModel(Connectable $a, Connectable $b): bool
    {
        return $a->getMorphClass() === $b->getMorphClass()
            && (string) $a->getKey() === (string) $b->getKey();
    }
}
