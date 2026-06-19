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

        $this->artisan('migrate', ['--database' => 'testing'])->run();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
        });
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
