<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Exceptions\InvalidStatusTransition;
use RoundlyConsulting\Connections\Exceptions\MissingConnectable;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Fixtures\SecondaryConnection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('connectAll creates a link to each connectable with shared permissions', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();

    $created = Connections::from($user)
        ->toMany([$teamA, $teamB])
        ->withPermissions('view')
        ->connectAll();

    expect($created)->toHaveCount(2)
        ->and($user->isConnectedTo($teamA))->toBeTrue()
        ->and($user->isConnectedTo($teamB))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($teamA, 'view'))->toBeTrue();
});

test('disconnectAll removes every staged connection', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->toMany([$teamA, $teamB])->connectAll();

    Connections::from($user)->toMany([$teamA, $teamB])->disconnectAll();

    expect($user->fresh()->isConnectedTo($teamA))->toBeFalse()
        ->and($user->fresh()->isConnectedTo($teamB))->toBeFalse();
});

test('grantAll adds permissions to every staged connectable', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->toMany([$teamA, $teamB])->connectAll();

    $results = Connections::from($user)->toMany([$teamA, $teamB])->grantAll('publish');

    expect($results)->toHaveCount(2)
        ->and($user->hasPermissionThroughConnection($teamA, 'publish'))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($teamB, 'publish'))->toBeTrue();
});

test('revokeAll removes permissions from every connected target', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->toMany([$teamA, $teamB])->withPermissions('publish')->connectAll();

    Connections::from($user)->toMany([$teamA, $teamB])->revokeAll('publish');

    expect($user->hasPermissionThroughConnection($teamA, 'publish', force: true))->toBeFalse()
        ->and($user->hasPermissionThroughConnection($teamB, 'publish', force: true))->toBeFalse();
});

test('revokeAll skips connectables without a connection', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->toMany([$teamA])->withPermissions('publish')->connectAll();

    $results = Connections::from($user)->toMany([$teamA, $teamB])->revokeAll('publish');

    expect($results)->toHaveCount(1);
});

test('connectAll accepts an iterable collection of connectables', function (): void {
    $user = User::create();
    $teams = collect([Team::create(), Team::create(), Team::create()]);

    $created = Connections::from($user)->toMany($teams)->connectAll();

    expect($created)->toHaveCount(3);
});

test('disconnectAll skips connectables that are not connected', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->toMany([$teamA])->connectAll();

    Connections::from($user)->toMany([$teamA, $teamB])->disconnectAll();

    expect($user->fresh()->isConnectedTo($teamA))->toBeFalse();
});

test('a bulk verb throws when no connectables are staged', function (): void {
    $user = User::create();

    Connections::from($user)->connectAll();
})->throws(MissingConnectable::class);

test('disconnectAll throws when no connectables are staged', function (): void {
    $user = User::create();

    Connections::from($user)->disconnectAll();
})->throws(MissingConnectable::class);

test('regression: a failed connectAll rolls back on the connection model\'s own database', function (): void {
    SecondaryConnection::install();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::between($user, $teamB)->connect();

    // teamB is already accepted, so staging it as pending throws after teamA was written.
    expect(fn () => Connections::from($user)->toMany([$teamA, $teamB])->asPending()->connectAll())
        ->toThrow(InvalidStatusTransition::class);

    expect(SecondaryConnection::query()->withTrashed()->where('connectable_id', $teamA->getKey())->exists())->toBeFalse()
        ->and(SecondaryConnection::query()->count())->toBe(1);
});

test('regression: a failed disconnectAll rolls back on the connection model\'s own database', function (): void {
    SecondaryConnection::install();

    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    Connections::from($user)->toMany([$teamA, $teamB])->connectAll();

    SecondaryConnection::deleting(function (SecondaryConnection $connection) use ($teamB): void {
        if ($connection->connectable_id === $teamB->getKey()) {
            throw new RuntimeException('boom');
        }
    });

    expect(fn () => Connections::from($user)->toMany([$teamA, $teamB])->disconnectAll())
        ->toThrow(RuntimeException::class, 'boom');

    expect(SecondaryConnection::query()->count())->toBe(2)
        ->and($user->isConnectedTo($teamA))->toBeTrue();
});
