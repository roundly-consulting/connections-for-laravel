<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Connections\Commands\MakeConnectableCommand;
use RoundlyConsulting\Connections\Commands\NotifyExpiringConnectionsCommand;
use RoundlyConsulting\Connections\Commands\PruneConnectionsCommand;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Support\ConnectionModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

final class ConnectionsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('connections')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasCommands([
                PruneConnectionsCommand::class,
                NotifyExpiringConnectionsCommand::class,
                MakeConnectableCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Model' => class_basename(ConnectionModel::class()),
                'Table' => ConnectionModel::table(),
                'Default status' => self::defaultStatus(),
                'Access checks' => Config::boolean('connections.enforce_active_on_check', true)
                    ? 'ENFORCED'
                    : 'ADVISORY',
                'Default expiry' => self::defaultExpiry(),
                // Permissions are the host's own ability strings — report how many
                // a new connection starts with, never which ones.
                'Default permissions' => self::defaultPermissions(),
                'Gate integration' => Config::boolean('connections.register_gate') ? 'ON' : 'OFF',
                'In-request cache' => Config::boolean('connections.cache.enabled', true) ? 'ON' : 'OFF',
                'Events' => Config::boolean('connections.events.enabled', true) ? 'ON' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ConnectionManager::class);

        // Scoped, not static: Octane (per request) and the queue worker (per job) drop
        // scoped instances between lifecycles, so a cached permission never outlives one.
        $this->app->scoped(Cache::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The connections migration keys its polymorphic columns through the toolkit's
        // `morphKey` macro, so it must exist before the migration runs. Registration is
        // idempotent — the toolkit guards it with `hasMacro()`.
        $this->registerBlueprintMacros();

        $this->registerGate();
    }

    /**
     * A global `Gate::before` hook that falls through to a connection's
     * permissions for *any* ability checked against a Connectable — not an
     * ability definition, so it stays hand-wired.
     */
    private function registerGate(): void
    {
        if (! Config::boolean('connections.register_gate')) {
            return;
        }

        Gate::before(function (mixed $user, string $ability, array $arguments): ?bool {
            $connectable = $arguments[0] ?? null;

            if (! $user instanceof Model || ! $user instanceof Connectable) {
                return null;
            }

            if (! $connectable instanceof Model || ! $connectable instanceof Connectable) {
                return null;
            }

            return $user->hasPermissionThroughConnection($connectable, $ability) ? true : null;
        });
    }

    private static function defaultStatus(): string
    {
        $status = config('connections.default_status');

        return is_string($status) && $status !== '' ? $status : 'accepted';
    }

    private static function defaultExpiry(): string
    {
        $expiry = config('connections.expiry.default');

        return match (true) {
            is_int($expiry), is_string($expiry) && ctype_digit(trim($expiry)) => trim((string) $expiry).'s',
            is_string($expiry) && $expiry !== '' => $expiry,
            default => 'NEVER',
        };
    }

    private static function defaultPermissions(): string
    {
        $permissions = config('connections.default_permissions', []);
        $count = is_array($permissions) ? count($permissions) : 0;

        return $count === 0 ? 'NONE' : $count.' granted on connect';
    }
}
