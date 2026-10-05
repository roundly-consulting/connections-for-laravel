<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

use function Pest\Laravel\assertDatabaseHas;

test('user can connect to other models with some permissions', function () {
    /** @var User $user */
    $user = User::create();

    /** @var Team $team */
    $team = Team::create();

    $createConnectionAction = new CreateConnection;

    $createConnectionAction->execute(
        connector: $user,
        connectable: $team,
        permissions: collect([
            'view',
        ]),
    );

    assertDatabaseHas('connections', [
        'connector_id' => $user->id,
        'connector_type' => User::class,
        'connectable_id' => $team->id,
        'connectable_type' => Team::class,
        'permissions' => $this->castAsJson(['view']),
    ]);
});

test('user can connect to other models with expiration', function () {
    /** @var User $user */
    $user = User::create();

    /** @var Team $team */
    $team = Team::create();

    $createConnectionAction = new CreateConnection;

    $createConnectionAction->execute(
        connector: $user,
        connectable: $team,
        expiresAt: today()->addDay(),
    );

    assertDatabaseHas('connections', [
        'connector_id' => $user->id,
        'connector_type' => User::class,
        'connectable_id' => $team->id,
        'connectable_type' => Team::class,
        'permissions' => $this->castAsJson([]),
        'expires_at' => today()->addDay(),
    ]);
});

test('it checks user permissions via connection to other models', function () {
    /** @var User $user */
    $user = User::create();

    /** @var Team $team */
    $team = Team::create();

    /** @var Team $anotherTeam */
    $anotherTeam = Team::create();

    $createConnectionAction = new CreateConnection;

    $createConnectionAction->execute(
        connector: $user,
        connectable: $team,
        permissions: collect([
            'view',
        ]),
    );

    expect($user)
        ->hasPermissionThroughConnection($team, 'view')->toBeTrue()
        ->hasPermissionThroughConnection($anotherTeam, 'view')->toBeFalse();
});

test('connectable has reference to connectors', function () {
    /** @var User $user */
    $user = User::create();

    /** @var Team $team */
    $team = Team::create();

    /** @var Team $anotherTeam */
    $anotherTeam = Team::create();

    $createConnectionAction = new CreateConnection;

    $createConnectionAction->execute($user, $team);

    expect($user)
        ->isConnectedTo($team)->toBeTrue()
        ->isConnectedTo($anotherTeam)->toBeFalse();

    expect($user->connections)
        ->toBeCollection()
        ->toHaveCount(1)
        ->first()->connectable->is($team);

    expect($team->connectors)
        ->toBeCollection()
        ->toHaveCount(1)
        ->first()->connector->is($user);
});

test('it is connected to any type', function () {
    /** @var User $user */
    $user = User::create();

    /** @var Team $team */
    $team = Team::create();

    $createConnectionAction = new CreateConnection;
    $createConnectionAction->execute($user, $team);

    expect($user)
        ->isConnectedToAny($team->getMorphClass())->toBeTrue()
        ->isConnectedToAny($user->getMorphClass())->toBeFalse();
});

test('connectable can get who is connected to them', function () {
    /** @var User $user */
    $user = User::create();

    /** @var User $anotherUser */
    $anotherUser = User::create();

    /** @var Team $team */
    $team = Team::create();

    $createConnectionAction = new CreateConnection;

    $createConnectionAction->execute($user, $team);

    expect($team)
        ->hasConnector($user)->toBeTrue()
        ->hasConnector($anotherUser)->toBeFalse();
});

test('connectable has any connector of type', function () {
    /** @var User $user */
    $user = User::create();

    /** @var User $anotherUser */
    $anotherUser = User::create();

    /** @var Team $team */
    $team = Team::create();

    $createConnectionAction = new CreateConnection;
    $createConnectionAction->execute($user, $team);

    expect($team)
        ->hasConnectorFromAny($user->getMorphClass())->toBeTrue()
        ->hasConnectorFromAny($anotherUser->getMorphClass())->toBeTrue()
        ->hasConnectorFromAny($team->getMorphClass())->toBeFalse();
});
