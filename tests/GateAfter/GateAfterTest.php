<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use RoundlyConsulting\Connections\Tests\Fixtures\TeamPolicy;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * `gate_mode` = `after`: the connection answers only when no policy, ability definition or
 * before hook did. Laravel keeps the first non-null result, so a deny always wins, however
 * broad the stored grant.
 */

beforeEach(function (): void {
    Gate::policy(Team::class, TeamPolicy::class);
});

test('a policy deny beats a stored wildcard', function (): void {
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['*']);

    expect(Gate::forUser($user)->allows('delete', $team))->toBeFalse()
        ->and(Gate::forUser($user)->allows('transfer', $team))->toBeFalse()
        ->and(Gate::forUser($user)->inspect('transfer', $team)->message())->toBe('Transfers are closed.');
});

test('a denying ability definition beats a stored wildcard', function (): void {
    Gate::define('archive', static fn (): bool => false);
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['*']);

    expect(Gate::forUser($user)->allows('archive', $team))->toBeFalse();
});

test('a host before hook that denies beats a stored wildcard', function (): void {
    Gate::before(static fn (mixed $user, string $ability): ?bool => $ability === 'publish' ? false : null);
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['*']);

    expect(Gate::forUser($user)->allows('publish', $team))->toBeFalse();
});

test('the connection answers where the policy has no opinion', function (): void {
    $user = User::create();
    $team = Team::create();

    // No grant yet: the policy's null and a missing method both stay a deny.
    expect(Gate::forUser($user)->allows('update', $team))->toBeFalse()
        ->and(Gate::forUser($user)->allows('publish', $team))->toBeFalse();

    $user->connectTo($team, ['update', 'publish']);

    expect(Gate::forUser($user)->allows('update', $team))->toBeTrue()
        ->and(Gate::forUser($user)->allows('publish', $team))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $team))->toBeFalse();
});

test('a policy allow stands without any connection', function (): void {
    expect(Gate::forUser(User::create())->allows('view', Team::create()))->toBeTrue();
});

test('gate wildcards off applies in after mode too', function (): void {
    config()->set('connections.gate_wildcards', false);
    $user = User::create();
    $everything = Team::create();
    $exact = Team::create();
    $user->connectTo($everything, ['*']);
    $user->connectTo($exact, ['publish']);

    expect(Gate::forUser($user)->allows('publish', $everything))->toBeFalse()
        ->and(Gate::forUser($user)->allows('publish', $exact))->toBeTrue();
});

test('the after hook ignores guests and non-connectable arguments', function (): void {
    $user = User::create();
    $team = Team::create();
    $user->connectTo($team, ['*']);

    expect(Gate::forUser(null)->allows('publish', $team))->toBeFalse()
        ->and(Gate::forUser($user)->allows('publish'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('publish', 'not-a-model'))->toBeFalse();
});
