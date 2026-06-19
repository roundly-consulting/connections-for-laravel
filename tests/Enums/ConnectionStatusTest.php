<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Enums\ConnectionStatus;

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
