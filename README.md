# Connections for Laravel

Many-to-many connections between Eloquent models, with per-connection **permissions** and
optional **expirations**. Connect any model to any other model — a user to a team, an
organization to a project — record what the connection is allowed to do, and let expired
connections prune themselves.

A fluent `Connections` facade, expressive trait verbs, lifecycle actions, events, and a
prune command make the common operations — connect, disconnect, grant, revoke, sync, check —
one readable line each.

## Requirements

- PHP 8.3+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/connections-for-laravel
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="connections-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="connections-config"
```

## Configuration

The package works with zero configuration. Publish `config/connections.php` to override any
of the keys below.

```php
return [

    // The Eloquent model used to represent a connection. Swap it for your own
    // subclass of RoundlyConsulting\Connections\Models\Connection to extend behaviour.
    'model' => RoundlyConsulting\Connections\Models\Connection::class,

    // The database table that stores connections. The migration reads this value.
    'table' => env('CONNECTIONS_TABLE', 'connections'),

    // In-request memoisation of resolved connections. Writes invalidate it automatically.
    'cache' => [
        'enabled' => env('CONNECTIONS_CACHE_ENABLED', true),
    ],

    // Dispatch lifecycle events as connections change.
    'events' => [
        'enabled' => env('CONNECTIONS_EVENTS_ENABLED', true),
    ],

    // Register a Gate::before check so $user->can('permission', $connectable) works.
    'register_gate' => env('CONNECTIONS_REGISTER_GATE', false),

];
```

| Key             | Type           | Default                | Env                          | Purpose |
|-----------------|----------------|------------------------|------------------------------|---------|
| `model`         | `class-string` | `Connection::class`    | —                            | Model class used when reading and writing connections. |
| `table`         | `string`       | `connections`          | `CONNECTIONS_TABLE`          | Table that stores connections; read by the migration. |
| `cache.enabled` | `bool`         | `true`                 | `CONNECTIONS_CACHE_ENABLED`  | In-request connection cache. Disable to always re-query. |
| `events.enabled`| `bool`         | `true`                 | `CONNECTIONS_EVENTS_ENABLED` | Dispatch lifecycle events. |
| `register_gate` | `bool`         | `false`                | `CONNECTIONS_REGISTER_GATE`  | Fall a host `Gate` check through to connection permissions. |

## Usage

### Make a model connectable

Add the `HasConnections` trait and implement the `Connectable` contract on any model that
should take part in connections:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Contracts\Connectable;

class User extends Model implements Connectable
{
    use HasConnections;
}

class Team extends Model implements Connectable
{
    use HasConnections;
}
```

A model can be both a **connector** (the side that initiates a connection) and a
**connectable** (the side a connection points to).

### The `Connections` facade (fluent builder)

The `Connections` facade is the most expressive way to work with connections:

```php
use RoundlyConsulting\Connections\Facades\Connections;

// Create or update a connection (idempotent), with permissions and an expiry.
Connections::between($user, $team)
    ->withPermissions('view', 'edit')
    ->expiresIn(now()->addMonth())   // CarbonInterface, CarbonInterval, or seconds
    ->connect();

// Defer the connectable until later with from()->to().
Connections::from($user)->to($team)->withPermissions('view')->connect();

// Permission lifecycle (grant/sync auto-create the connection if absent).
Connections::between($user, $team)->grant('publish');
Connections::between($user, $team)->revoke('publish');
Connections::between($user, $team)->sync('view', 'edit');   // exact set

// Move the expiry, or clear it with null.
Connections::between($user, $team)->extend(now()->addYear());
Connections::between($user, $team)->extend(null);

// Checks.
Connections::between($user, $team)->exists();      // bool
Connections::between($user, $team)->can('edit');   // bool

// Remove the connection (soft delete).
Connections::between($user, $team)->disconnect();

// Remove all expired connections, returning how many were removed.
$removed = Connections::prune();
```

The cache is invalidated automatically on every write, so a check after a write reflects the
change without any `force` flag.

### Trait verbs

If you prefer model methods, `HasConnections` exposes the same lifecycle:

```php
$user->connectTo($team, ['view', 'edit'], now()->addMonth()); // Connection
$user->disconnectFrom($team);                                  // void
$user->grantThroughConnection($team, 'publish');               // Connection
$user->revokeThroughConnection($team, 'publish');              // Connection
$user->syncConnectionPermissions($team, ['view']);             // Connection
$user->permissionsThroughConnection($team);                    // Collection<int, string>
```

### Inspecting connections

```php
$user->isConnectedTo($team);                       // bool
$user->isConnectedToAny($team->getMorphClass());   // bool
$team->hasConnector($user);                        // bool
$team->hasConnectorFromAny($user->getMorphClass());// bool

$user->connections;  // connections this model initiated
$team->connectors;   // connections pointing at this model
```

### Checking permissions

```php
$user->hasPermissionThroughConnection($team, 'edit');             // bool
$user->hasPermissionThroughConnection($team, 'edit', force: true);// bypass the cache
$connection->hasPermission('edit');                               // bool on a Connection
```

### Actions

Each operation is also a standalone action you can resolve from the container and unit test.
`CreateConnection::execute()` keeps its original positional signature for backward
compatibility.

```php
use RoundlyConsulting\Connections\Actions\CreateConnection;
use RoundlyConsulting\Connections\Actions\GrantPermissions;
use RoundlyConsulting\Connections\Actions\RevokePermissions;
use RoundlyConsulting\Connections\Actions\SyncPermissions;
use RoundlyConsulting\Connections\Actions\ExtendConnection;
use RoundlyConsulting\Connections\Actions\DisconnectConnection;
use RoundlyConsulting\Connections\Actions\PruneConnections;

app(CreateConnection::class)->execute($user, $team, collect(['view']), now()->addMonth());
app(GrantPermissions::class)->execute($user, $team, 'publish');
app(RevokePermissions::class)->execute($user, $team, 'publish');
app(SyncPermissions::class)->execute($user, $team, 'view', 'edit');
app(ExtendConnection::class)->execute($user, $team, now()->addYear());
app(DisconnectConnection::class)->execute($user, $team);
app(PruneConnections::class)->execute();
```

`grant` and `sync` auto-create the connection when none exists. `disconnect`, `revoke`, and
`extend` throw `RoundlyConsulting\Connections\Exceptions\ConnectionNotFound` when there is no
connection.

### Events

When `connections.events.enabled` is true (the default), the following events are dispatched:

```php
use RoundlyConsulting\Connections\Events\ConnectionCreated;
use RoundlyConsulting\Connections\Events\ConnectionUpdated;
use RoundlyConsulting\Connections\Events\ConnectionRemoved;
use RoundlyConsulting\Connections\Events\ConnectionPermissionsChanged;
```

Each carries the affected `Connection`; `ConnectionPermissionsChanged` also carries the
`$previous` and `$current` permission lists.

### Gate integration (opt-in)

Set `connections.register_gate` to `true` to register a `Gate::before` check so a host app
can authorize through connections:

```php
$user->can('publish', $team); // true when $user has the 'publish' permission to $team
```

It is off by default so it never surprises a host's own authorization.

### Pruning expired connections

A connection whose `expires_at` is in the past is prunable. Remove expired connections with
the package command:

```bash
php artisan connections:prune
```

Laravel's native mass pruning still works too:

```bash
php artisan model:prune --model="RoundlyConsulting\Connections\Models\Connection"
```

## Public API

| Type      | Class / member                                                            |
|-----------|---------------------------------------------------------------------------|
| Facade    | `RoundlyConsulting\Connections\Facades\Connections`                       |
| Manager   | `RoundlyConsulting\Connections\ConnectionManager`                         |
| Builder   | `RoundlyConsulting\Connections\PendingConnection`                         |
| Actions   | `Actions\CreateConnection`, `DisconnectConnection`, `GrantPermissions`, `RevokePermissions`, `SyncPermissions`, `ExtendConnection`, `PruneConnections` |
| DTOs      | `DataTransferObjects\ConnectionData`, `DataTransferObjects\PermissionSet` |
| Events    | `Events\ConnectionCreated`, `ConnectionUpdated`, `ConnectionRemoved`, `ConnectionPermissionsChanged` |
| Exceptions| `Exceptions\ConnectionNotFound`, `Exceptions\MissingConnectable`          |
| Command   | `connections:prune` (`Commands\PruneConnectionsCommand`)                  |
| Model     | `RoundlyConsulting\Connections\Models\Connection`                         |
| Trait     | `RoundlyConsulting\Connections\Concerns\HasConnections`                   |
| Contract  | `RoundlyConsulting\Connections\Contracts\Connectable`                     |
| Factory   | `RoundlyConsulting\Connections\Database\Factories\ConnectionFactory`      |

> The legacy `RoundlyConsulting\Connections\Connection` model class and
> `RoundlyConsulting\Connections\Interfaces\Connectable` interface remain as
> backward-compatible aliases. New code should use the `Models\` and `Contracts\` names.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
