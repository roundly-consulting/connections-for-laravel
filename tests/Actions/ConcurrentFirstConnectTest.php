<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Fixtures\QueryLog;
use RoundlyConsulting\Connections\Tests\Fixtures\Race\Barrier;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use Symfony\Component\Process\Process;

/*
 * Regression (2026-10-05 chat review, C-5): the create path read the missing pair
 * `for update` before inserting. On InnoDB (REPEATABLE READ) a locking read that matches
 * nothing takes a gap lock; gap locks do not conflict with each other, so two first
 * connects both got one and then each blocked the other's insert — a deadlock (1213)
 * instead of the unique-violation fallback that applies the loser to the winner's row.
 */

test('regression: a first connect takes no row lock before its insert', function (): void {
    $user = User::create();
    $team = Team::create();

    $log = QueryLog::start();

    Connections::between($user, $team)->connect();

    $insert = $log->firstIndex(static fn (array $entry): bool => str_starts_with($entry['sql'], 'insert'));
    $lock = $log->firstIndex(static fn (array $entry): bool => $entry['locked']);

    expect($insert)->not->toBeNull()
        ->and($lock === null || $lock > $insert)->toBeTrue();
});

test('an existing pair is still read for update inside the write transaction', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->connect();

    $log = QueryLog::start();

    Connections::between($user, $team)->withPermissions('view')->connect();

    $lock = $log->firstIndex(static fn (array $entry): bool => $entry['locked']);
    $update = $log->firstIndex(static fn (array $entry): bool => str_starts_with($entry['sql'], 'update'));

    expect($lock)->not->toBeNull()
        ->and($update)->toBeGreaterThan($lock)
        ->and($log->entries[$lock]['level'])->toBeGreaterThanOrEqual(1)
        ->and($log->entries[$update]['level'])->toBe($log->entries[$lock]['level']);
});

test('regression: two concurrent first connects on MySQL both succeed on one row', function (): void {
    $user = User::create();
    $team = Team::create();

    $directory = sys_get_temp_dir().'/connections-race-'.Str::random(12);
    mkdir($directory);

    $child = new Process(
        [PHP_BINARY, __DIR__.'/../Fixtures/Race/connect.php', $directory, (string) $user->getKey(), (string) $team->getKey(), 'edit'],
        timeout: 60,
    );

    try {
        $child->start();

        (new Barrier($directory, 'parent', 'child'))->arm();

        $parent = Barrier::attempt(static fn () => Connections::between($user, $team)->withPermissions('view')->connect());

        $child->wait();

        /** @var array{ok: bool, id: int|string|null, error: string|null}|null $other */
        $other = json_decode($child->getOutput(), true);
    } finally {
        $child->stop();
        array_map(unlink(...), glob($directory.'/*') ?: []);
        rmdir($directory);
    }

    expect($other)->toBeArray('child process said: '.$child->getOutput().$child->getErrorOutput())
        ->and($parent['error'])->toBeNull()
        ->and($other['error'] ?? null)->toBeNull()
        ->and($other['id'] ?? null)->toBe($parent['id'])
        ->and(Connection::query()->count())->toBe(1)
        ->and(Connection::query()->sole()->permissions->all())->toBeIn([['view'], ['edit']]);
})->skip(fn (): bool => DriverMatrix::driver() !== 'mysql', 'needs MySQL (TESTING_DB_DRIVER=mysql)');
