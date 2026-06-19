# Connections for Laravel

Many-to-many connections between Eloquent models, with per-connection **permissions** and
optional **expirations**. Connect any model to any other model — a user to a team, an
organization to a project — record what the connection is allowed to do, and let expired
connections prune themselves.

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

The published config file (`config/connections.php`) exposes a single key:

```php
return [

    // The Eloquent model used to represent a connection. Swap it for your own
    // subclass of RoundlyConsulting\Connections\Connection if you need to extend
    // the default behaviour.
    'model' => RoundlyConsulting\Connections\Connection::class,

];
```

| Key     | Type            | Default        | Purpose                                                        |
|---------|-----------------|----------------|----------------------------------------------------------------|
| `model` | `class-string`  | `Connection::class` | The model class used when reading and writing connections. |

## Usage

### Make a model connectable

Add the `HasConnections` trait and implement the `Connectable` interface on any model that
should take part in connections:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Concerns\HasConnections;
use RoundlyConsulting\Connections\Interfaces\Connectable;

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

### Create a connection

Use the `CreateConnection` action. Pass an optional collection of permission strings and an
optional expiration. Creating a connection between the same two models again updates it
rather than duplicating it.

```php
use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\Actions\CreateConnection;

$connection = (new CreateConnection)->execute(
    connector: $user,
    connectable: $team,
    permissions: collect(['view', 'edit']),
    expiresAt: Carbon::now()->addMonth(),
);
```

### Inspect connections

The `HasConnections` trait adds query helpers to the connector and connectable sides:

```php
// Does $user have a connection pointing at $team?
$user->isConnectedTo($team);          // bool

// Is $user connected to any model of a given morph type?
$user->isConnectedToAny($team->getMorphClass());

// From the connectable side: is $user one of $team's connectors?
$team->hasConnector($user);           // bool

// Does $team have any connector of a given morph type?
$team->hasConnectorFromAny($user->getMorphClass());

// Eloquent relations are available too:
$user->connections;  // connections this model initiated
$team->connectors;   // connections pointing at this model
```

### Check permissions

Permissions are stored per connection. Check whether a connector holds a permission through
its connection to a given connectable:

```php
$user->hasPermissionThroughConnection($team, 'edit'); // bool
```

Lookups are cached in memory for the duration of the request. Pass `force: true` to bypass
the cache and re-query after changing a connection:

```php
$user->hasPermissionThroughConnection($team, 'edit', force: true);
```

You can also check a permission directly on a `Connection` instance:

```php
$connection->hasPermission('edit'); // bool
```

### Expiring connections

A connection with an `expires_at` in the past is considered prunable. The `Connection` model
uses Laravel's mass pruning, so you can remove expired connections with the scheduler or by
running:

```bash
php artisan model:prune --model="RoundlyConsulting\Connections\Connection"
```

## Public API

| Type      | Class / member                                                  |
|-----------|-----------------------------------------------------------------|
| Action    | `RoundlyConsulting\Connections\Actions\CreateConnection`        |
| Model     | `RoundlyConsulting\Connections\Connection`                      |
| Trait     | `RoundlyConsulting\Connections\Concerns\HasConnections`         |
| Interface | `RoundlyConsulting\Connections\Interfaces\Connectable`          |
| Factory   | `RoundlyConsulting\Connections\Database\Factories\ConnectionFactory` |

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
