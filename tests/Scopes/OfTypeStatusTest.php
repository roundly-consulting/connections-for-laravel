<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, LOW): connectorsOfType() / connectablesOfType() listed
 * blocked and pending connections, unlike hasConnector() / isConnectedTo().
 */

test('connectorsOfType() lists only active connectors while access checks enforce it', function (): void {
    $team = Team::create();
    $active = User::create();
    $blocked = User::create();
    $pending = User::create();
    Connections::between($active, $team)->connect();
    Connections::between($blocked, $team)->connect();
    $team->blockConnectionFrom($blocked);
    Connections::between($pending, $team)->invite();

    expect($team->connectorsOfType(User::class)->pluck('id')->all())->toBe([$active->id]);

    config()->set('connections.enforce_active_on_check', false);

    expect($team->connectorsOfType(User::class))->toHaveCount(3);
});

test('connectablesOfType() lists only active connectables while access checks enforce it', function (): void {
    $user = User::create();
    $active = Team::create();
    $expired = Team::create();
    $pending = Team::create();
    Connections::between($user, $active)->connect();
    Connections::between($user, $expired)->expiresIn(now()->subMinute())->connect();
    Connections::between($user, $pending)->invite();

    expect($user->connectablesOfType(Team::class)->pluck('id')->all())->toBe([$active->id]);

    config()->set('connections.enforce_active_on_check', false);

    expect($user->connectablesOfType(Team::class))->toHaveCount(3);
});
