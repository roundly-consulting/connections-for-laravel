<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Support\ConnectionModel;
use RoundlyConsulting\Connections\Tests\Fixtures\QueryLog;
use RoundlyConsulting\Connections\Tests\Fixtures\SecondaryConnection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-10-05 chat review, C-1): grant / revoke / sync / clear read the pair's
 * permissions and wrote them back with no transaction and no row lock, so a concurrent
 * revoke('admin') + grant('view') could bring the revoked permission back.
 *
 * Limitation: a single PHP process cannot block itself on a row lock, so the race is not
 * replayed here. What is pinned is the structure that prevents it: the row is read
 * `for update` inside a transaction on the connection model's own database, and the write
 * lands in that same transaction, before it commits.
 */

dataset('permission writes', [
    'grant' => [fn (User $user, Team $team) => Connections::between($user, $team)->permissions()->grant('edit')],
    'revoke' => [fn (User $user, Team $team) => Connections::between($user, $team)->permissions()->revoke('admin')],
    'sync' => [fn (User $user, Team $team) => Connections::between($user, $team)->permissions()->sync('edit')],
    'clear' => [fn (User $user, Team $team) => Connections::between($user, $team)->permissions()->clear()],
]);

dataset('model databases', [
    'the default connection' => [false],
    'a secondary connection' => [true],
]);

test('regression: a permission write locks the row and writes it in one transaction on the model\'s database', function (Closure $write, bool $secondary): void {
    if ($secondary) {
        SecondaryConnection::install();
    }

    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('admin', 'view')->connect();

    $database = (new (ConnectionModel::class()))->getConnection()->getName();
    $log = QueryLog::start($database);

    $write($user, $team);

    $lock = $log->firstIndex(static fn (array $entry): bool => $entry['locked']);
    $update = $log->firstIndex(static fn (array $entry): bool => str_starts_with($entry['sql'], 'update'));

    expect($lock)->not->toBeNull('the row is never read for update')
        ->and($log->entries[$lock]['connection'])->toBe($database)
        ->and($log->entries[$lock]['level'])->toBeGreaterThanOrEqual(1)
        ->and($update)->toBeGreaterThan($lock)
        ->and($log->entries[$update]['connection'])->toBe($database)
        ->and($log->entries[$update]['level'])->toBe($log->entries[$lock]['level'])
        ->and(array_column(array_slice($log->entries, $lock, $update - $lock), 'sql'))->not->toContain('commit');
})->with('permission writes')->with('model databases');
