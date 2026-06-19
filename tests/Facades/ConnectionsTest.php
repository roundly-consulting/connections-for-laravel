<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\ConnectionManager;
use RoundlyConsulting\Connections\Facades\Connections;

test('the facade resolves the connection manager', function (): void {
    expect(Connections::getFacadeRoot())->toBeInstanceOf(ConnectionManager::class);
});
