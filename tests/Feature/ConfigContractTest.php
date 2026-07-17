<?php

declare(strict_types=1);

/**
 * The config contract connections never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied, alerts
 *    #24's thrice-documented `escalation` key. The stakes are the same shape here —
 *    `enforce_active_on_check` claims that expired and blocked links grant nothing, which
 *    is an access-control promise.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/connections.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // `connections.model` is read through the toolkit's `ModelResolver::for()` seam in
        // ConnectionModel rather than a `config()` call. It is a real read — it drives the
        // whole model swap — but it is not a `config(` token, so the prefix is what makes
        // it visible to the scraper.
        'extraReadPrefixes' => ['connections.'],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure calls
        // `config('connections.…')` for real (register_gate, cache.enabled,
        // events.enabled, enforce_active_on_check), and for several of those it is a
        // genuine reader. Excluding it would discard readers and weaken the reverse
        // direction for nothing.
    ]);
});
