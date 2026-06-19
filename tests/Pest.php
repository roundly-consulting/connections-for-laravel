<?php

use RoundlyConsulting\Connections\Tests\GateTestCase;
use RoundlyConsulting\Connections\Tests\TestCase;

uses(TestCase::class)->in(
    'Actions',
    'ArchTest.php',
    'CacheInvalidationTest.php',
    'CacheTest.php',
    'Commands',
    'ConfigTest.php',
    'ConnectionManagerTest.php',
    'ConnectionTest.php',
    'DataTransferObjects',
    'EventsTest.php',
    'Facades',
    'HasConnectionsTest.php',
    'PendingConnectionTest.php',
);

uses(GateTestCase::class)->in('Gate');
