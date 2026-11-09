<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Tests\Fixtures\TeamPolicy;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

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

/*
 * `before` mode is today's default and stays it until the next major: a connection that
 * grants the ability answers ahead of every policy. Pinned so the default can't change by
 * accident; `after` mode (tests/GateAfter) is the one that never overrides a deny.
 */
test('in before mode a stored wildcard overrides a policy deny', function (): void {
    Gate::policy(Team::class, TeamPolicy::class);
    $user = User::create();
    $team = Team::create();

    expect(Gate::forUser($user)->allows('delete', $team))->toBeFalse();

    $user->connectTo($team, ['*']);

    expect(Gate::forUser($user)->allows('delete', $team))->toBeTrue();
});

test('with gate wildcards off only an exact grant answers through the gate', function (): void {
    config()->set('connections.gate_wildcards', false);
    $user = User::create();
    $everything = Team::create();
    $posts = Team::create();
    $exact = Team::create();

    $user->connectTo($everything, ['*']);
    $user->connectTo($posts, ['posts.*']);
    $user->connectTo($exact, ['posts.delete']);

    expect(Gate::forUser($user)->allows('delete', $everything))->toBeFalse()
        ->and(Gate::forUser($user)->allows('*', $everything))->toBeFalse()
        ->and(Gate::forUser($user)->allows('posts.delete', $posts))->toBeFalse()
        ->and(Gate::forUser($user)->allows('posts.*', $posts))->toBeFalse()
        ->and(Gate::forUser($user)->allows('posts.delete', $exact))->toBeTrue()
        ->and(Gate::forUser($user)->allows('posts.update', $exact))->toBeFalse()
        // The direct checks keep honouring wildcards: the option is about the Gate only.
        ->and($user->hasPermissionThroughConnection($everything, 'delete'))->toBeTrue()
        ->and($user->hasPermissionThroughConnection($posts, 'posts.delete'))->toBeTrue();
});

test('with gate wildcards off an exact grant on an inactive connection still answers nothing', function (): void {
    config()->set('connections.gate_wildcards', false);
    $user = User::create();
    $team = Team::create();

    Connections::between($user, $team)->withPermissions('publish')->invite();

    expect(Gate::forUser($user)->allows('publish', $team))->toBeFalse();
});

test('an invalid gate wildcards value throws when the gate asks', function (): void {
    config()->set('connections.gate_wildcards', 'sometimes');
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['publish']);

    Gate::forUser($user)->allows('publish', $team);
})->throws(InvalidConfigurationException::class, 'connections.gate_wildcards');
