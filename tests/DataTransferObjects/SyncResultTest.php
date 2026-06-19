<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\DataTransferObjects\SyncResult;

test('it exposes the attached, detached and updated ids', function (): void {
    $result = new SyncResult(attached: [1], detached: [2], updated: [3]);

    expect($result->attached)->toBe([1])
        ->and($result->detached)->toBe([2])
        ->and($result->updated)->toBe([3])
        ->and($result->isEmpty())->toBeFalse();
});

test('an empty result reports itself as empty', function (): void {
    expect((new SyncResult)->isEmpty())->toBeTrue();
});
