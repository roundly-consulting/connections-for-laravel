<?php

declare(strict_types=1);

/*
 * The second writer of the concurrent-first-connect race (see ConcurrentFirstConnectTest).
 * Boots the package on a bare Testbench app against the suite's database — the
 * TESTING_DB_* environment is inherited from the test process — and connects the pair
 * behind the barrier. Prints its outcome as JSON.
 *
 * Usage: php connect.php <barrier-dir> <user-id> <team-id> <permission>
 */

use Orchestra\Testbench\Foundation\Application;
use RoundlyConsulting\Connections\ConnectionsServiceProvider;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Fixtures\Race\Barrier;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;

require __DIR__.'/../../../vendor/autoload.php';

[, $directory, $userId, $teamId, $permission] = $argv;

$app = Application::create(options: ['extra' => ['dont-discover' => ['*']]]);

DriverMatrix::configure($app);
$app->register(ConnectionsServiceProvider::class);

(new Barrier($directory, 'child', 'parent'))->arm();

echo json_encode(Barrier::attempt(static fn () => Connections::between(
    User::query()->findOrFail($userId),
    Team::query()->findOrFail($teamId),
)->withPermissions($permission)->connect()));
