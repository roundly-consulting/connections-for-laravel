<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Connections\Commands\MakeConnectableCommand;
use RoundlyConsulting\Connections\Commands\NotifyExpiringConnectionsCommand;
use RoundlyConsulting\Connections\Commands\PruneConnectionsCommand;
use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;

it('registers every publish tag', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(ConnectionsServiceProvider::class, $tag))->not->toBeEmpty();
})->with([
    'connections-config',
    'connections-migrations',
]);

it('publishes the config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(ConnectionsServiceProvider::class, 'connections-config');

    expect($paths)->toBe([
        realpath(__DIR__.'/../config/connections.php') => config_path('connections.php'),
    ]);
});

// Kept for its unique half only: `toPublishMigrationsTimestamped` (tests/MigrationOrderTest.php)
// pins the count and that every destination is a timestamped migration path, but its regex
// accepts any file name. This pins *which* source file is published, which nothing else does.
it('publishes the connections migration under its own name', function (): void {
    $paths = ServiceProvider::pathsToPublish(ConnectionsServiceProvider::class, 'connections-migrations');

    $source = (string) array_key_first($paths);
    $target = (string) reset($paths);

    expect(basename($source))->toBe('create_connections_table.php')
        ->and(dirname($target))->toBe(database_path('migrations'))
        ->and(basename($target))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_connections_table\.php$/');
});

// 'never auto-loads its migrations' is deleted: it hand-rolled the migrator-paths check that
// `toNotAutoLoadMigrations` now performs in tests/MigrationOrderTest.php. Both went red together
// when the provider was made to really loadMigrationsFrom(), so this was a pure duplicate.

it('binds the connection manager as a shared singleton', function (): void {
    expect(app(ConnectionManager::class))->toBe(app('connections'))
        ->toBeInstanceOf(ConnectionManager::class);
});

it('registers the package commands', function (string $signature, string $class): void {
    expect(Artisan::all())->toHaveKey($signature)
        ->and(Artisan::all()[$signature])->toBeInstanceOf($class);
})->with([
    ['connections:prune', PruneConnectionsCommand::class],
    ['connections:notify-expiring', NotifyExpiringConnectionsCommand::class],
    ['make:connectable', MakeConnectableCommand::class],
]);

it('contributes a connections section to about', function (string $expected): void {
    $this->artisan('about --only=connections')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Connections',
    'Model',
    'Table',
    'Default status',
    'Access checks',
    'Default expiry',
    'Default permissions',
    'Gate integration',
    'In-request cache',
    'Events',
]);

it('reports advisory access checks in about when enforcement is off', function (): void {
    config()->set('connections.enforce_active_on_check', false);

    $this->artisan('about --only=connections')
        ->expectsOutputToContain('ADVISORY')
        ->assertExitCode(0);
});

it('reports the default expiry in about when one is configured', function (): void {
    config()->set('connections.expiry.default', '30 days');

    $this->artisan('about --only=connections')
        ->expectsOutputToContain('30 days')
        ->assertExitCode(0);
});

it('reports a numeric default expiry in seconds', function (): void {
    config()->set('connections.expiry.default', 3600);

    $this->artisan('about --only=connections')
        ->expectsOutputToContain('3600s')
        ->assertExitCode(0);
});

it('reports the default permissions as a count, never the ability names', function (): void {
    config()->set('connections.default_permissions', ['billing.refund', 'posts.publish']);

    $this->artisan('about --only=connections')
        ->expectsOutputToContain('2 granted on connect')
        ->doesntExpectOutputToContain('billing.refund')
        ->doesntExpectOutputToContain('posts.publish')
        ->assertExitCode(0);
});

it('reports no default permissions when none are configured', function (): void {
    $this->artisan('about --only=connections')
        ->expectsOutputToContain('NONE')
        ->assertExitCode(0);
});
