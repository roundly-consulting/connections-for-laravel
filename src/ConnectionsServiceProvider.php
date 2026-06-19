<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Illuminate\Support\ServiceProvider;

final class ConnectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/connections.php', 'connections');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/connections.php' => config_path('connections.php'),
            ], 'connections-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'connections-migrations');
        }
    }
}
