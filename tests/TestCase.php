<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider connections needs. `enums-for-laravel` is a hard `require` but ships
     * no provider (it is a helpers-only package), so the list is genuinely one entry —
     * not an omission.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [ConnectionsServiceProvider::class];
    }

    /**
     * The connections migration, named by provider class (never by filename), plus the
     * host-owned fixture tables the connectors and connectables live in.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            ConnectionsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }
}
