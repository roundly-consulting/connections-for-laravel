<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Testing\ConnectionFake;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('fake returns a ConnectionFake and records connects', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();

    expect($fake)->toBeInstanceOf(ConnectionFake::class);
    $fake->assertConnected($user, $team);
});

test('operations still hit the database (pass-through)', function (): void {
    Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    expect($user->isConnectedTo($team))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($team, 'view'))->toBeTrue();
});

test('assertNotConnected passes when nothing was recorded', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    $fake->assertNotConnected($user, $team);
});

test('assertConnected fails when no connect was recorded', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    $fake->assertConnected($user, $team);
})->throws(AssertionFailedError::class);

test('it records invitations, acceptances and blocks', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->invite();
    Connections::between($user, $team)->accept();

    $fake->assertInvited($user, $team);
    $fake->assertAccepted($user, $team);

    Connections::between($user, $team)->block();
    $fake->assertBlocked($user, $team);
});

test('it asserts a permission was granted through a recorded operation', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('publish')->connect();

    $fake->assertHasPermissionThrough($user, $team, 'publish');
});

test('assertHasPermissionThrough fails for an unrecorded permission', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('view')->connect();

    $fake->assertHasPermissionThrough($user, $team, 'publish');
})->throws(AssertionFailedError::class);

test('assertConnectedTimes counts connect and invite operations', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    Connections::between($user, $teamA)->connect();
    Connections::between($user, $teamB)->invite();

    $fake->assertConnectedTimes(2);
});

test('disconnect, grant and revoke are recorded', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->connect();
    Connections::between($user, $team)->grant('view');
    Connections::between($user, $team)->revoke('view');
    Connections::between($user, $team)->disconnect();

    expect($fake->recorded())->toHaveCount(4);
});

test('assertNotConnected passes for a different pair than the one recorded', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();
    $otherTeam = Team::create();

    Connections::between($user, $team)->connect();

    $fake->assertNotConnected($user, $otherTeam);
});

test('assertConnected ignores operations from a different connector', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $otherUser = User::create();
    $team = Team::create();

    Connections::between($otherUser, $team)->connect();

    $fake->assertNotConnected($user, $team);
});

test('from() defers the connectable and still records', function (): void {
    $fake = Connections::fake();

    $user = User::create();
    $team = Team::create();

    Connections::from($user)->to($team)->connect();

    $fake->assertConnected($user, $team);
});
