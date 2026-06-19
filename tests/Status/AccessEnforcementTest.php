<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('a pending connection does not grant permission when enforcing active', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->invite();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeFalse();
});

test('a blocked connection does not grant permission', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->connect();
    Connections::between($user, $team)->block();

    expect($user->hasPermissionThroughConnection($team, 'view', force: true))->toBeFalse();
});

test('an expired connection does not grant permission when enforcing active', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)
        ->withPermissions('view')
        ->expiringAt(now()->subDay())
        ->connect();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeFalse();
});

test('an expired connection still grants permission when enforcement is off', function (): void {
    config()->set('connections.enforce_active_on_check', false);

    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)
        ->withPermissions('view')
        ->expiringAt(now()->subDay())
        ->connect();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();
});

test('an active connection grants permission', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('view')->connect();

    expect($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();
});

test('isConnectedTo excludes pending links when enforcing active', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    expect($user->isConnectedTo($team))->toBeFalse();

    $team->acceptConnectionFrom($user);

    expect($user->fresh()->isConnectedTo($team))->toBeTrue();
});

test('isConnectedTo counts any link when enforcement is off', function (): void {
    config()->set('connections.enforce_active_on_check', false);

    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->invite();

    expect($user->isConnectedTo($team))->toBeTrue();
});
