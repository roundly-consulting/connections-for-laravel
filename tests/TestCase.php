<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
        });
    }

    /**
     * Migrations are publish-only — the provider loads none — so the suite runs
     * the package's own migrations explicitly.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ConnectionsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
