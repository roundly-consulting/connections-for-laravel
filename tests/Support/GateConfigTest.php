<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Support\ConnectionsConfig;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('runs the gate before policies unless after is set', function (mixed $mode, bool $after): void {
    config()->set('connections.gate_mode', $mode);

    expect(ConnectionsConfig::gateRunsAfterPolicies())->toBe($after);
})->with([
    'not set' => [null, false],
    'blank' => ['', false],
    'before' => ['before', false],
    'after' => ['after', true],
]);

it('refuses an unknown gate mode', function (): void {
    config()->set('connections.gate_mode', 'sideways');

    ConnectionsConfig::gateRunsAfterPolicies();
})->throws(InvalidConfigurationException::class, 'connections.gate_mode');

it('honours wildcards through the gate unless switched off', function (mixed $value, bool $expected): void {
    config()->set('connections.gate_wildcards', $value);

    expect(ConnectionsConfig::gateHonoursWildcards())->toBe($expected);
})->with([
    'not set' => [null, true],
    'on' => [true, true],
    'off' => [false, false],
    'env off' => ['off', false],
]);
