<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Facades\Connections;

/*
 * The facade contract, pinned: the docblock matches ConnectionManager's public API and the
 * accessor is its class-string; fake() is real, a ConnectionManager subtype, and takes over DI
 * too; and every host-facing action under src/Actions is reachable from the facade (14 of 14 —
 * the three Actions/Concerns helpers are traits, not actions).
 */
it('keeps the facade complete, fakeable and covering every action', function (): void {
    expect(Connections::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});
