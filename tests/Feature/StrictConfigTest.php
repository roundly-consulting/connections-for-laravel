<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/*
 | A typo in a host's config or env must fail loudly, never quietly become a default. A
 | default expiry that was neither a string nor an int used to mean "never expires", a
 | non-array permission list used to grant nothing, and a non-string table name used to
 | become `connections`. Each now throws, naming the key.
 */

afterEach(fn () => Carbon::setTestNow());

it('refuses a default expiry that is not an interval or a positive number of seconds (strict config)', function (mixed $expiry): void {
    config()->set('connections.expiry.default', $expiry);

    expect(fn () => Connections::between(User::create(), Team::create())->connect())
        ->toThrow(InvalidConfigurationException::class, 'connections.expiry.default')
        ->and(Connection::query()->count())->toBe(0);
})->with([
    'word' => 'thirty days',
    'typo' => '30 dayz',
    'decimal seconds' => '1.5',
    'zero seconds' => 0,
    'zero string' => '0',
    'negative seconds' => -60,
    'negative interval' => '-30 days',
    'zero interval' => '0 days',
    'bool' => true,
    'array' => [['30 days']],
]);

it('reads a canonical seconds string, an interval and an absent expiry (strict config)', function (): void {
    Carbon::setTestNow('2026-10-03 12:00:00');

    config()->set('connections.expiry.default', ' 60 ');
    $seconds = Connections::between(User::create(), Team::create())->connect();

    config()->set('connections.expiry.default', '2 hours');
    $interval = Connections::between(User::create(), Team::create())->connect();

    config()->set('connections.expiry.default', null);
    $never = Connections::between(User::create(), Team::create())->connect();

    expect($seconds->expires_at?->toDateTimeString())->toBe('2026-10-03 12:01:00')
        ->and($interval->expires_at?->toDateTimeString())->toBe('2026-10-03 14:00:00')
        ->and($never->expires_at)->toBeNull();
});

it('refuses a default permission list that is not a list of names (strict config)', function (mixed $permissions): void {
    config()->set('connections.default_permissions', $permissions);

    expect(fn () => Connections::between(User::create(), Team::create())->connect())
        ->toThrow(InvalidConfigurationException::class, 'connections.default_permissions')
        ->and(Connection::query()->count())->toBe(0);
})->with([
    'a string' => 'view',
    'an integer entry' => [['view', 5]],
    'a blank entry' => [['view', ' ']],
    'a nested list' => [[['view']]],
]);

it('grants nothing when the default permission list is absent (strict config)', function (): void {
    config()->set('connections.default_permissions', null);

    expect(Connections::between(User::create(), Team::create())->connect()->permissions->all())->toBe([]);
});

it('refuses a blank or non-string table (strict config)', function (mixed $table): void {
    config()->set('connections.table', $table);

    expect(fn () => (new Connection)->getTable())
        ->toThrow(InvalidConfigurationException::class, 'connections.table');
})->with(['blank' => '', 'whitespace' => '  ', 'array' => [['connections']], 'integer' => 42]);

it('uses the conventional table when none is configured (strict config)', function (): void {
    config()->set('connections.table', null);

    expect((new Connection)->getTable())->toBe('connections');
});

it('refuses to migrate onto a blank table name (strict config)', function (): void {
    config()->set('connections.table', '');

    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/create_connections_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'connections.table');
});

it('keeps the about section rendering on a malformed host config (strict config)', function (): void {
    config()->set('connections.default_status', 'acepted');
    config()->set('connections.expiry.default', 'thirty days');
    config()->set('connections.default_permissions', 'billing.refund');

    expect('connections')->toLeakNoSecrets(
        secrets: ['acepted', 'thirty days', 'billing.refund'],
        mustRender: ['Default status', 'Default expiry', 'Default permissions', 'INVALID'],
    );
});
