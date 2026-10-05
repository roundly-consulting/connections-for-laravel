<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('it auto-creates the connection when none exists', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = app(GrantPermissions::class)->execute($user, $team, 'view');

    expect($connection->hasPermission('view'))->toBeTrue();
});

test('it adds a new permission to an existing connection', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    $connection = app(GrantPermissions::class)->execute($user, $team, 'edit');

    expect($connection->permissions->all())->toBe(['view', 'edit']);
});

test('granting an existing permission is a net no-op', function (): void {
    $user = User::create();
    $team = Team::create();

    app(CreateConnection::class)->execute($user, $team, collect(['view']));

    $connection = app(GrantPermissions::class)->execute($user, $team, 'view');

    expect($connection->permissions->all())->toBe(['view']);
});

test('regression: a grant that loses the create race adds to the winner\'s permissions', function (): void {
    $user = User::create();
    $team = Team::create();

    // The "other request": right after grant('edit') first reads the pair (and finds
    // nothing), a concurrent grant('view') creates it.
    $raced = false;
    DB::listen(function (QueryExecuted $query) use (&$raced, $user, $team): void {
        if ($raced || preg_match('/^select .* from [`"]?connections[`"]?/i', $query->sql) !== 1) {
            return;
        }

        $raced = true;

        app(GrantPermissions::class)->execute($user, $team, 'view');
    });

    $connection = app(GrantPermissions::class)->execute($user, $team, 'edit');

    expect($raced)->toBeTrue()
        ->and($connection->fresh()?->permissions->all())->toBe(['view', 'edit']);
});
