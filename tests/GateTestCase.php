<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

abstract class GateTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('connections.register_gate', true);
    }
}
