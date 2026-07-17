<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

/**
 * The suite's base case with the Gate::before integration switched on before boot.
 *
 * This used to override `defineEnvironment()` and call `parent::defineEnvironment($app)`
 * first — correct, but fragile now that the base case does its whole job there
 * (DriverMatrix::configure + configBeforeBoot + model swaps): dropping that one parent
 * call would decapitate the base case silently, with no error and no red.
 * `configBeforeBoot()` feeds the same before-boot window with nothing to forget.
 *
 * The `array_merge(parent::configBeforeBoot(), …)` matters for the same reason one level
 * down. The parent is empty today; that is not a reason to omit it.
 */
abstract class GateTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'connections.register_gate' => true,
        ]);
    }
}
