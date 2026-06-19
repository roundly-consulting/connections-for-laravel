<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Connections\Commands\MakeConnectableCommand;
use RoundlyConsulting\Connections\Commands\NotifyExpiringConnectionsCommand;
use RoundlyConsulting\Connections\Commands\PruneConnectionsCommand;
use RoundlyConsulting\Connections\Contracts\Connectable;

final class ConnectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/connections.php', 'connections');

        $this->app->singleton(ConnectionManager::class);
        $this->app->alias(ConnectionManager::class, 'connections');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerGate();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneConnectionsCommand::class,
                NotifyExpiringConnectionsCommand::class,
                MakeConnectableCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/connections.php' => config_path('connections.php'),
            ], 'connections-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'connections-migrations');
        }
    }

    private function registerGate(): void
    {
        if (! (bool) config('connections.register_gate', false)) {
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
}
