<?php

declare(strict_types=1);

use RoundlyConsulting\Connections\Models\Connection;

return [

    /*
    |--------------------------------------------------------------------------
    | Connection Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to represent a connection. Override this if you
    | need to extend the default model with your own behaviour. The replacement
    | must extend RoundlyConsulting\Connections\Models\Connection.
    |
    */

    'model' => Connection::class,

    /*
    |--------------------------------------------------------------------------
    | Connections Table
    |--------------------------------------------------------------------------
    |
    | The database table that stores connections. The shipped migration reads
    | this value, so changing it here keeps the schema and model in sync.
    |
    */

    'table' => env('CONNECTIONS_TABLE', 'connections'),

    /*
    |--------------------------------------------------------------------------
    | In-request Cache
    |--------------------------------------------------------------------------
    |
    | When enabled, resolved connections are memoised for the lifetime of a
    | single request so repeated permission checks don't re-query. Writes
    | invalidate the relevant entry automatically. Disable to always hit the
    | database.
    |
    */

    'cache' => [
        'enabled' => env('CONNECTIONS_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | When enabled, lifecycle events are dispatched as connections are created,
    | updated, removed, or have their permissions changed. Disable to suppress
    | all package events.
    |
    */

    'events' => [
        'enabled' => env('CONNECTIONS_EVENTS_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate Integration
    |--------------------------------------------------------------------------
    |
    | When true, a Gate::before check is registered so host apps can call
    | $user->can('permission', $connectable) and have it fall through to the
    | connection's permissions. Off by default to avoid surprising host
    | authorization.
    |
    */

    'register_gate' => env('CONNECTIONS_REGISTER_GATE', false),

];
