<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\DataTransferObjects\ConnectionData;
use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('fromModels maps morph keys and permissions', function (): void {
    $user = User::create();
    $team = Team::create();

    $data = ConnectionData::fromModels($user, $team, ['view', 'edit'], now()->addDay());

    expect($data->connectorType)->toBe($user->getMorphClass())
        ->and($data->connectorId)->toBe($user->getKey())
        ->and($data->connectableType)->toBe($team->getMorphClass())
        ->and($data->connectableId)->toBe($team->getKey())
        ->and($data->permissions->all())->toBe(['view', 'edit'])
        ->and($data->expiresAt)->not->toBeNull();
});

test('fromModels accepts a collection of permissions', function (): void {
    $user = User::create();
    $team = Team::create();

    $data = ConnectionData::fromModels($user, $team, collect(['publish']));

    expect($data->permissions->all())->toBe(['publish']);
});

test('fromModels accepts a permission set', function (): void {
    $user = User::create();
    $team = Team::create();

    $data = ConnectionData::fromModels($user, $team, PermissionSet::make('view'));

    expect($data->permissions->all())->toBe(['view']);
});

test('fromModels leaves permissions null when none are supplied', function (): void {
    $user = User::create();
    $team = Team::create();

    $data = ConnectionData::fromModels($user, $team);

    expect($data->permissions)->toBeNull()
        ->and($data->expiresAt)->toBeNull()
        ->and($data->status)->toBeNull()
        ->and($data->meta)->toBeNull();
});

test('it exposes connector and connectable key arrays', function (): void {
    $data = new ConnectionData(
        connectorType: 'user',
        connectorId: 1,
        connectableType: 'team',
        connectableId: 2,
    );

    expect($data->connectorKeys())->toBe(['connector_id' => 1, 'connector_type' => 'user'])
        ->and($data->connectableKeys())->toBe(['connectable_id' => 2, 'connectable_type' => 'team']);
});
