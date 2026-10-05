<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('meta is persisted on create', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)
        ->withMeta(['invited_by' => 'admin', 'note' => 'hello'])
        ->connect();

    expect($connection->meta)->toBe(['invited_by' => 'admin', 'note' => 'hello']);
});

test('meta supports dot-access through the accessor', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)
        ->withMeta(['role' => ['label' => 'owner']])
        ->connect();

    expect($connection->meta('role.label'))->toBe('owner')
        ->and($connection->meta('missing', 'fallback'))->toBe('fallback');
});

test('re-connecting merges meta by default', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withMeta(['a' => 1])->connect();
    $connection = Connections::between($user, $team)->withMeta(['b' => 2])->connect();

    expect($connection->meta)->toBe(['a' => 1, 'b' => 2]);
});

test('replaceMeta overwrites the stored meta on re-connect', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withMeta(['a' => 1])->connect();
    $connection = Connections::between($user, $team)->withMeta(['b' => 2])->replaceMeta()->connect();

    expect($connection->meta)->toBe(['b' => 2]);
});

test('connecting without meta leaves existing meta untouched', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withMeta(['a' => 1])->connect();
    $connection = Connections::between($user, $team)->connect();

    expect($connection->meta)->toBe(['a' => 1]);
});

test('null meta is fine', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = Connections::between($user, $team)->connect();

    expect($connection->meta)->toBeNull()
        ->and($connection->meta('anything'))->toBeNull();
});

test('connectTo accepts a meta argument', function (): void {
    $user = User::create();
    $team = Team::create();

    $connection = $user->connectTo($team, ['view'], null, ['source' => 'import']);

    expect($connection->meta)->toBe(['source' => 'import']);
});

test('regression: replaceMeta overwrites the stored meta through connectAll', function (): void {
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withMeta(['old' => 1])->connect();

    $connections = Connections::from($user)->toMany([$team])->withMeta(['new' => 2])->replaceMeta()->connectAll();

    expect($connections->first()?->meta)->toBe(['new' => 2])
        ->and(Connections::between($user, $team)->find()?->meta)->toBe(['new' => 2]);
});
