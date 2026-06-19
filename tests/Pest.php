<?php

use RoundlyConsulting\Connections\Tests\GateTestCase;
use RoundlyConsulting\Connections\Tests\TestCase;

uses(TestCase::class)->in(
    'Actions',
    'ArchTest.php',
    'Bulk',
    'CacheInvalidationTest.php',
    'CacheTest.php',
    'Commands',
    'ConfigTest.php',
    'ConnectionManagerTest.php',
    'ConnectionTest.php',
    'DataTransferObjects',
    'Dx',
    'Enums',
    'EventsTest.php',
    'Facades',
    'HasConnectionsTest.php',
    'Metadata',
    'PendingConnectionTest.php',
    'Permissions',
    'Scopes',
    'Status',
    'Testing',
);

uses(GateTestCase::class)->in('Gate');
