<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

test('the gate falls through to connection permissions when enabled', function (): void {
    $user = User::create();
    $team = Team::create();

    $user->connectTo($team, ['publish']);

    expect(Gate::forUser($user)->allows('publish', $team))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $team))->toBeFalse();
});

test('the gate ignores non-connectable arguments', function (): void {
    $user = User::create();

    expect(Gate::forUser($user)->allows('publish'))->toBeFalse();
});
