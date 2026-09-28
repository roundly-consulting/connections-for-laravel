<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;
use RoundlyConsulting\Connections\Models\Connection;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/*
 * Regression (2026-09-28 review, LOW): connectionsWithPermission() / the withPermission scope
 * ignored wildcards and status, unlike hasPermissionThroughConnection().
 */

test('connectionsWithPermission() honours the * and segment wildcards', function (): void {
    $user = User::create();
    $star = Team::create();
    $segment = Team::create();
    $exact = Team::create();
    $other = Team::create();
    Connections::between($user, $star)->withPermissions('*')->connect();
    Connections::between($user, $segment)->withPermissions('posts.*')->connect();
    Connections::between($user, $exact)->withPermissions('posts.publish')->connect();
    Connections::between($user, $other)->withPermissions('users.*')->connect();

    $ids = $user->connectionsWithPermission('posts.publish')->pluck('connectable_id')->all();

    expect($ids)->toEqualCanonicalizing([$star->id, $segment->id, $exact->id])
        ->and($user->hasPermissionThroughConnection($star, 'posts.publish'))->toBeTrue();
});

test('connectionsWithPermission() counts only active connections while access checks enforce it', function (): void {
    $user = User::create();
    $active = Team::create();
    $pending = Team::create();
    Connections::between($user, $active)->withPermissions('publish')->connect();
    Connections::between($user, $pending)->withPermissions('publish')->invite();

    expect($user->connectionsWithPermission('publish')->pluck('connectable_id')->all())->toBe([$active->id]);

    config()->set('connections.enforce_active_on_check', false);

    expect($user->connectionsWithPermission('publish')->count())->toBe(2);
});

test('the withPermission scope matches nested segment wildcards', function (): void {
    $user = User::create();
    $team = Team::create();
    Connections::between($user, $team)->withPermissions('posts.comments.*')->connect();

    expect(Connection::query()->withPermission('posts.comments.delete')->count())->toBe(1)
        ->and(Connection::query()->withPermission('posts.edit')->count())->toBe(0);
});
