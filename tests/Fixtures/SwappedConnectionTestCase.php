<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests\Fixtures;

use RoundlyConsulting\Connections\Tests\TestCase;

/**
 * The suite's base case with `connections.model` already pointed at {@see CountedConnection}
 * BEFORE the providers boot.
 *
 * Boot order is the whole point: the provider hangs its Gate integration and the model
 * hangs its relations on whatever `connections.model` names at boot. A `config()->set()`
 * inside a test body reads back correctly but leaves that wiring on the packaged
 * Connection — which is the shape that let media #28 ship, and precisely what the test
 * this replaces (`ConfigTest`'s "a custom connection model is honoured", a runtime `set`
 * plus an `instanceof`) could never have caught.
 *
 * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * whatever the base case wires, the same decapitation an un-parented `defineEnvironment()`
 * override causes one level up.
 */
abstract class SwappedConnectionTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'connections.model' => CountedConnection::class,
        ]);
    }
}
