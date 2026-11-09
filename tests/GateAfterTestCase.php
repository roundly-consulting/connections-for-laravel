<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

/**
 * The Gate integration in `after` mode, set before boot because the provider picks the hook
 * (Gate::before or Gate::after) while it boots.
 */
abstract class GateAfterTestCase extends GateTestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'connections.gate_mode' => 'after',
        ]);
    }
}
