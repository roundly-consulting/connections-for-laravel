<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('hasAnyPermissionThroughConnection checks the connection', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->connect();

    expect($user->hasAnyPermissionThroughConnection($team, 'view', 'edit'))->toBeTrue()
        ->and($user->hasAnyPermissionThroughConnection($team, 'delete'))->toBeFalse();
});

test('hasAllPermissionsThroughConnection requires all permissions', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view', 'edit')->connect();

    expect($user->hasAllPermissionsThroughConnection($team, 'view', 'edit'))->toBeTrue()
        ->and($user->hasAllPermissionsThroughConnection($team, 'view', 'delete'))->toBeFalse();
});

test('clearPermissions on the builder revokes everything', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view', 'edit')->connect();

    $connection = Connections::between($user, $team)->clearPermissions();

    expect($connection->permissions->all())->toBe([]);
});

test('clearConnectionPermissions on the trait revokes everything', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->connect();

    $connection = $user->clearConnectionPermissions($team);

    expect($connection->permissions->all())->toBe([]);
});
