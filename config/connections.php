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

    /*
    |--------------------------------------------------------------------------
    | Default Permissions
    |--------------------------------------------------------------------------
    |
    | Permissions applied to a new connection when the caller supplies none at
    | all. An explicit empty array still means "no permissions" — only an
    | absent/null permission set falls back to this list.
    |
    */

    'default_permissions' => [],

    /*
    |--------------------------------------------------------------------------
    | Default Status
    |--------------------------------------------------------------------------
    |
    | The status a connection is created with when none is specified. The
    | default of "accepted" keeps connect() producing immediately-live links.
    | Set to "pending" to model an invitation flow by default. One of:
    | pending, accepted, blocked.
    |
    */

    'default_status' => env('CONNECTIONS_DEFAULT_STATUS', 'accepted'),

    /*
    |--------------------------------------------------------------------------
    | Enforce Active On Check
    |--------------------------------------------------------------------------
    |
    | When true, access checks (hasPermissionThroughConnection / can) and
    | isConnectedTo only count connections that are active — accepted and not
    | expired. Pending, blocked, and expired links therefore grant nothing.
    | Set to false to treat expiry/status as advisory (the pre-v1.1 behaviour).
    |
    */

    'enforce_active_on_check' => env('CONNECTIONS_ENFORCE_ACTIVE_ON_CHECK', true),

    /*
    |--------------------------------------------------------------------------
    | Default Expiry
    |--------------------------------------------------------------------------
    |
    | When set and the caller supplies no expiry, new connections expire after
    | this interval. Accepts a relative string ("30 days", "2 weeks") or an
    | integer number of seconds. Null means connections never expire by default.
    |
    */

    'expiry' => [
        'default' => env('CONNECTIONS_EXPIRY_DEFAULT'),
    ],

];
