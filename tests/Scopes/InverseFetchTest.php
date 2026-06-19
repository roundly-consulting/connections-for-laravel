<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('connectablesOfType returns the connected models of that class', function (): void {
    $user = User::create();
    $teamA = Team::create();
    $teamB = Team::create();
    $user->connectTo($teamA);
    $user->connectTo($teamB);

    $teams = $user->connectablesOfType(Team::class);

    expect($teams)->toHaveCount(2)
        ->and($teams->first())->toBeInstanceOf(Team::class);
});

test('connectablesOfType filters out other types', function (): void {
    $user = User::create();
    $team = Team::create();
    $other = User::create();
    $user->connectTo($team);
    $user->connectTo($other);

    expect($user->connectablesOfType(Team::class))->toHaveCount(1)
        ->and($user->connectablesOfType(User::class))->toHaveCount(1);
});

test('connectablesOfType accepts a morph alias', function (): void {
    Relation::morphMap(['team' => Team::class]);

    $user = User::create();
    $team = Team::create();
    $user->connectTo($team);

    expect($user->connectablesOfType('team'))->toHaveCount(1);
})->after(fn () => Relation::morphMap([], false));

test('connectorsOfType returns the connectors of that class', function (): void {
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team);

    $connectors = $team->connectorsOfType(User::class);

    expect($connectors)->toHaveCount(1)
        ->and($connectors->first())->toBeInstanceOf(User::class);
});

test('connectablesOfType returns empty when there are no matches', function (): void {
    $user = User::create();

    expect($user->connectablesOfType(Team::class))->toHaveCount(0);
});
