<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Tests\Fixtures\SwappedConnectionTestCase;
use RoundlyConsulting\Connections\Tests\GateTestCase;
use RoundlyConsulting\Connections\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the Gate and ModelSwap directories below need
// different base cases, and a blanket bind would claim them first. ArchTest.php is listed
// because `swappableModelsAreNotFinal` reads the `connections.model` config default and so
// needs the app booted — an arch file is not automatically test-cased.
uses(TestCase::class)->in(
    'Actions',
    'ArchTest.php',
    'Bulk',
    'CacheInvalidationTest.php',
    'CacheTest.php',
    'Commands',
    'ConfigTest.php',
    'ConnectionManagerTest.php',
    'ConnectionsServiceProviderTest.php',
    'ConnectionTest.php',
    'DataTransferObjects',
    'Dx',
    'Enums',
    'EventsTest.php',
    'Facades',
    'Feature',
    'HasConnectionsTest.php',
    'Metadata',
    'MigrationOrderTest.php',
    'PendingConnectionTest.php',
    'Permissions',
    'Scopes',
    'Status',
    'Support',
    'Testing',
);

uses(GateTestCase::class)->in('Gate');

// The model-swap proofs need `connections.model` pointed at the host subclass BEFORE the
// providers boot, so they run on their own base case in their own directory — Pest binds
// a test case per directory, not per file.
uses(SwappedConnectionTestCase::class)->in('ModelSwap');
