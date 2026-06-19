<?php

use RoundlyConsulting\Connections\Cache;

it('manages cache', function () {
    $key = fake()->password;

    expect(Cache::has($key))->toBeFalse()
        ->and(Cache::get($key))->toBeNull()
        ->and(Cache::put($key, 'works'))->toBe('works')
        ->and(Cache::has($key))->toBeTrue()
        ->and(Cache::get($key))->toBe('works');

    Cache::flush();

    expect(Cache::has($key))->toBeFalse();

    Cache::put($key, 'yep');

    expect(Cache::has($key))->toBeTrue();

    Cache::forget($key);

    expect(Cache::has($key))->toBeFalse();
});
