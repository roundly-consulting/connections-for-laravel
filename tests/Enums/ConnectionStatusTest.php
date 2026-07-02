<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;

test('it is backed by the expected string values', function (): void {
    expect(ConnectionStatus::Pending->value)->toBe('pending')
        ->and(ConnectionStatus::Accepted->value)->toBe('accepted')
        ->and(ConnectionStatus::Blocked->value)->toBe('blocked');
});

test('transitioning to the same state is always allowed', function (): void {
    foreach (ConnectionStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeTrue();
    }
});

test('accept is allowed from pending and blocked but not produced from accepted-to-pending', function (): void {
    expect(ConnectionStatus::Pending->canTransitionTo(ConnectionStatus::Accepted))->toBeTrue()
        ->and(ConnectionStatus::Blocked->canTransitionTo(ConnectionStatus::Accepted))->toBeTrue();
});

test('block is allowed from any state', function (): void {
    expect(ConnectionStatus::Pending->canTransitionTo(ConnectionStatus::Blocked))->toBeTrue()
        ->and(ConnectionStatus::Accepted->canTransitionTo(ConnectionStatus::Blocked))->toBeTrue();
});

test('moving back to pending is not allowed', function (): void {
    expect(ConnectionStatus::Accepted->canTransitionTo(ConnectionStatus::Pending))->toBeFalse()
        ->and(ConnectionStatus::Blocked->canTransitionTo(ConnectionStatus::Pending))->toBeFalse();
});

test('it exposes the backed values via the shared enums helper', function (): void {
    expect(ConnectionStatus::values()->all())->toBe(['pending', 'accepted', 'blocked']);
});

test('it builds a laravel in-rule from the backed values', function (): void {
    expect(ConnectionStatus::validationRule())->toBe('in:pending,accepted,blocked');
});

test('it exposes readable labels per case', function (): void {
    expect(ConnectionStatus::Pending->readable())->toBe('Pending')
        ->and(ConnectionStatus::Accepted->readable())->toBe('Accepted')
        ->and(ConnectionStatus::Blocked->readable())->toBe('Blocked')
        ->and(ConnectionStatus::labels()->all())->toBe(['Pending', 'Accepted', 'Blocked']);
});

test('it maps value to label for select inputs', function (): void {
    expect(ConnectionStatus::toOptions()->all())->toBe([
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'blocked' => 'Blocked',
    ]);
});

test('it exposes option DTOs for JS selects', function (): void {
    $options = ConnectionStatus::options();

    expect($options)->toHaveCount(3)
        ->and($options->first())->toBeInstanceOf(EnumOption::class)
        ->and($options->first()->value)->toBe('pending')
        ->and($options->first()->label)->toBe('Pending')
        ->and($options->first()->name)->toBe('Pending');
});

test('it resolves a case from its readable label', function (): void {
    expect(ConnectionStatus::tryFromLabel('Accepted'))->toBe(ConnectionStatus::Accepted)
        ->and(ConnectionStatus::tryFromLabel('nope'))->toBeNull();
});
