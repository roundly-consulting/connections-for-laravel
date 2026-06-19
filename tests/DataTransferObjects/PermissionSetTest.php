<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;

test('it de-duplicates permissions on construction', function (): void {
    $set = new PermissionSet(['view', 'view', 'edit']);

    expect($set->all())->toBe(['view', 'edit']);
});

test('make builds from variadic arguments', function (): void {
    expect(PermissionSet::make('a', 'b')->all())->toBe(['a', 'b']);
});

test('fromIterable accepts collections and arrays', function (): void {
    expect(PermissionSet::fromIterable(collect(['x', 'y']))->all())->toBe(['x', 'y']);
});

test('add returns a new set with the permissions', function (): void {
    $set = PermissionSet::make('view');
    $next = $set->add('edit', 'view');

    expect($set->all())->toBe(['view'])
        ->and($next->all())->toBe(['view', 'edit']);
});

test('remove returns a new set without the permissions', function (): void {
    $set = PermissionSet::make('view', 'edit');

    expect($set->remove('edit')->all())->toBe(['view']);
});

test('has reports membership', function (): void {
    $set = PermissionSet::make('view');

    expect($set->has('view'))->toBeTrue()
        ->and($set->has('edit'))->toBeFalse();
});

test('isEmpty reflects emptiness', function (): void {
    expect((new PermissionSet)->isEmpty())->toBeTrue()
        ->and(PermissionSet::make('view')->isEmpty())->toBeFalse();
});

test('toCollection returns the permissions', function (): void {
    expect(PermissionSet::make('a', 'b')->toCollection()->all())->toBe(['a', 'b']);
});
