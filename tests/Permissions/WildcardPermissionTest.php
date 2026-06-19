<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;

function permissionConnection(array $permissions): Connection
{
    return Connection::factory()->make(['permissions' => collect($permissions)]);
}

test('a global wildcard grants any permission', function (): void {
    $connection = permissionConnection(['*']);

    expect($connection->hasPermission('posts.edit'))->toBeTrue()
        ->and($connection->hasPermission('anything'))->toBeTrue();
});

test('a segment wildcard matches within its namespace only', function (): void {
    $connection = permissionConnection(['posts.*']);

    expect($connection->hasPermission('posts.edit'))->toBeTrue()
        ->and($connection->hasPermission('posts.delete'))->toBeTrue()
        ->and($connection->hasPermission('users.edit'))->toBeFalse();
});

test('an exact set is unaffected by wildcard logic', function (): void {
    $connection = permissionConnection(['view', 'edit']);

    expect($connection->hasPermission('view'))->toBeTrue()
        ->and($connection->hasPermission('delete'))->toBeFalse();
});

test('hasAnyPermission returns true when at least one matches', function (): void {
    $connection = permissionConnection(['view']);

    expect($connection->hasAnyPermission('view', 'edit'))->toBeTrue()
        ->and($connection->hasAnyPermission('delete', 'edit'))->toBeFalse()
        ->and($connection->hasAnyPermission())->toBeFalse();
});

test('hasAllPermissions requires every permission', function (): void {
    $connection = permissionConnection(['view', 'edit']);

    expect($connection->hasAllPermissions('view', 'edit'))->toBeTrue()
        ->and($connection->hasAllPermissions('view', 'delete'))->toBeFalse()
        ->and($connection->hasAllPermissions())->toBeTrue();
});

test('a global wildcard satisfies any and all checks', function (): void {
    $connection = permissionConnection(['*']);

    expect($connection->hasAnyPermission('a', 'b'))->toBeTrue()
        ->and($connection->hasAllPermissions('a', 'b'))->toBeTrue();
});
