<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/connections-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel">
    <img src="art/hero.png" alt="Connections for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/connections-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/connections-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/connections-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/connections-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/connections-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/connections-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
</p>
<!-- roundly-badges:end -->

# Connections for Laravel

Many-to-many connections between Eloquent models, with per-connection **permissions** and
optional **expirations**. Connect any model to any other model — a user to a team, an
organization to a project — record what the connection is allowed to do, and let expired
connections prune themselves.

A fluent `Connections` facade, expressive trait verbs, lifecycle actions, events, and a
prune command make the common operations — connect, disconnect, grant, revoke, sync, check —
one readable line each. Connections can also model **invitation flows** (pending → accepted /
blocked), carry free-form **metadata**, be operated on in **bulk**, and matched with
**wildcard permissions** and **query scopes**.

> **Behaviour change in 1.1:** access checks now require a connection to be **active**
> (accepted and not expired) by default (`connections.enforce_active_on_check`). An expired or
> non-accepted connection no longer grants permission. Set the flag to `false` to restore the
> pre-1.1 "expiry is advisory" behaviour.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/connections-for-laravel
```

Publish and run the migration. The package **does not load its migration automatically** — it is
copied into your `database/migrations` (timestamped) when you publish it, so your app owns it and
`php artisan migrate` runs exactly what you published:

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

    // Permissions applied to a new connection when the caller supplies none.
    'default_permissions' => [],

    // Status a connection is created with when none is given (pending|accepted|blocked).
    'default_status' => env('CONNECTIONS_DEFAULT_STATUS', 'accepted'),

    // When true, access checks only count active (accepted + not expired) connections.
    'enforce_active_on_check' => env('CONNECTIONS_ENFORCE_ACTIVE_ON_CHECK', true),

    // Default expiry for new connections: a relative string ("30 days") or seconds.
    'expiry' => [
        'default' => env('CONNECTIONS_EXPIRY_DEFAULT'),
    ],

];
```

| Key                       | Type            | Default             | Env                                | Purpose |
|---------------------------|-----------------|---------------------|------------------------------------|---------|
| `model`                   | `class-string`  | `Connection::class` | —                                  | Model class used when reading and writing connections. |
| `table`                   | `string`        | `connections`       | `CONNECTIONS_TABLE`                | Table that stores connections; read by the migration. |
| `cache.enabled`           | `bool`          | `true`              | `CONNECTIONS_CACHE_ENABLED`        | In-request connection cache. Disable to always re-query. |
| `events.enabled`          | `bool`          | `true`              | `CONNECTIONS_EVENTS_ENABLED`       | Dispatch lifecycle events. |
| `register_gate`           | `bool`          | `false`             | `CONNECTIONS_REGISTER_GATE`        | Fall a host `Gate` check through to connection permissions. |
| `default_permissions`     | `list<string>`  | `[]`                | —                                  | Permissions applied when a connection is created with none. An explicit empty set stays empty. |
| `default_status`          | `string`        | `accepted`          | `CONNECTIONS_DEFAULT_STATUS`       | Status new connections start in (`pending`/`accepted`/`blocked`). |
| `enforce_active_on_check` | `bool`          | `true`              | `CONNECTIONS_ENFORCE_ACTIVE_ON_CHECK` | Require accepted + not-expired for access checks. `false` = pre-1.1 behaviour. |
| `expiry.default`          | `string\|int\|null` | `null`          | `CONNECTIONS_EXPIRY_DEFAULT`       | Default expiry applied when none is given. |

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

### Invitations (pending → accepted / blocked)

Model an approval flow by creating a connection in the `pending` state and letting the
receiving side accept or block it:

```php
// Connector side: send an invitation (a pending connection).
Connections::between($user, $team)->withPermissions('view')->invite();

// Receiving side accepts or blocks.
$team->acceptConnectionFrom($user);  // status → accepted (now active)
$team->blockConnectionFrom($user);   // status → blocked (grants nothing)

// Or drive it from the connector / builder.
Connections::between($user, $team)->accept();
Connections::between($user, $team)->block();
$user->inviteConnection($team);

// Model helpers.
$connection->isPending();
$connection->isAccepted();
$connection->isBlocked();
$connection->isActive();   // accepted AND not expired
```

Only **active** connections grant permissions and count for `isConnectedTo` while
`enforce_active_on_check` is on (the default).

### Connection metadata

Attach free-form context to a connection with a JSON `meta` bag:

```php
Connections::between($user, $team)
    ->withMeta(['invited_by' => $admin->id, 'role' => ['label' => 'owner']])
    ->connect();

$connection->meta('role.label');           // 'owner' (dot access)
$connection->meta('missing', 'fallback');  // 'fallback'

// Re-connecting merges meta by default; replaceMeta() overwrites instead.
Connections::between($user, $team)->withMeta(['note' => 'hi'])->connect();          // merge
Connections::between($user, $team)->withMeta(['note' => 'hi'])->replaceMeta()->connect();

// The trait verb takes meta too.
$user->connectTo($team, ['view'], now()->addMonth(), ['source' => 'import']);
```

### Bulk operations

Operate on many connectables at once, or reconcile an exact set:

```php
// Touch many targets in one call (each still emits its own event).
Connections::from($user)->toMany([$teamA, $teamB])->withPermissions('view')->connectAll();
Connections::from($user)->toMany($teams)->disconnectAll();
Connections::from($user)->toMany($teams)->grantAll('publish');
Connections::from($user)->toMany($teams)->revokeAll('publish');

// Reconcile to exactly this set: connect missing, disconnect extras, update overlap.
$result = $user->syncConnections([$teamA, $teamC]);
$result->attached;  // list of newly connected ids
$result->detached;  // list of disconnected ids
$result->updated;   // list of ids that already existed

// Per-target attributes via a map or SyncTarget DTOs.
use RoundlyConsulting\Connections\DataTransferObjects\SyncTarget;

$user->syncConnections([
    ['model' => $teamA, 'permissions' => ['view', 'edit']],
    new SyncTarget($teamB, ['publish'], now()->addDays(30)),
]);
```

### Toggle, reconnect & restore

```php
// Connect if absent, disconnect if present.
Connections::between($user, $team)->toggle();   // ?Connection
$user->toggleConnection($team);

// Restore a previously soft-deleted connection (or connect afresh if none trashed),
// firing ConnectionRestored. Avoids the unique-index collision a fresh insert would hit.
Connections::between($user, $team)->reconnect();
Connections::between($user, $team)->restore();  // alias
$user->reconnectTo($team);
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
$user->hasAnyPermissionThroughConnection($team, 'view', 'edit');  // bool
$user->hasAllPermissionsThroughConnection($team, 'view', 'edit'); // bool

$connection->hasPermission('edit');             // bool on a Connection
$connection->hasAnyPermission('view', 'edit');  // bool
$connection->hasAllPermissions('view', 'edit'); // bool

// Clear every permission on a connection.
Connections::between($user, $team)->clearPermissions();
$user->clearConnectionPermissions($team);
```

#### Wildcard permissions

A connection whose permission set contains `*` passes any permission check, and a segment
wildcard like `posts.*` matches `posts.edit`. Sets without a wildcard behave exactly as before
(this is purely opt-in by the stored data).

```php
Connections::between($user, $team)->withPermissions('posts.*')->connect();
$user->hasPermissionThroughConnection($team, 'posts.edit');  // true
$user->hasPermissionThroughConnection($team, 'users.edit');  // false
```

### Query scopes & typed fetches

```php
// Constrained relations (return MorphMany you can chain ->get()/->count()).
$user->activeConnections();                 // accepted + not expired
$user->expiredConnections();                // past expiry
$user->expiringConnections($days = 7);      // expiring within N days
$user->connectionsWithPermission('publish');

// Eloquent scopes on the model / relation.
$user->connections()->active()->get();
$user->connections()->expiringSoon(14)->get();

// Fetch the connected models of a given type (FQCN or morph alias), not the Connection rows.
$user->connectablesOfType(Team::class);   // Collection<Team>
$team->connectorsOfType(User::class);     // Collection<User>
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
use RoundlyConsulting\Connections\Events\ConnectionInvited;   // a pending connection was created
use RoundlyConsulting\Connections\Events\ConnectionAccepted;  // status → accepted
use RoundlyConsulting\Connections\Events\ConnectionBlocked;   // status → blocked
use RoundlyConsulting\Connections\Events\ConnectionRestored;  // a soft-deleted link was restored
use RoundlyConsulting\Connections\Events\ConnectionExpiring;  // dispatched by notify-expiring
```

Each carries the affected `Connection`; `ConnectionPermissionsChanged` also carries the
`$previous` and `$current` permission lists, and `ConnectionExpiring` carries
`$daysUntilExpiry`. Lean on these events for auditing — for example, a listener on
`ConnectionAccepted` that writes to your own audit log; the package ships no audit table.

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

### Notifying about expiring connections

Schedule the opt-in command and listen for `ConnectionExpiring` to notify before a connection
lapses (no new table — it leans entirely on the event):

```bash
php artisan connections:notify-expiring --days=7
```

### Generating a connectable model

Scaffold a new model that is already `Connectable`:

```bash
php artisan make:connectable Organisation        # writes app/Models/Organisation.php
php artisan make:connectable User --existing      # prints the two lines to add to an existing model
```

### Testing helper

`Connections::fake()` swaps the manager for a recording fake — operations still hit your test
database (so reads work), but calls are captured for assertions, in the spirit of
`Bus::fake()`:

```php
use RoundlyConsulting\Connections\Facades\Connections;

$fake = Connections::fake();

Connections::between($user, $team)->withPermissions('publish')->connect();

$fake->assertConnected($user, $team);
$fake->assertHasPermissionThrough($user, $team, 'publish');
$fake->assertInvited($user, $team);
$fake->assertAccepted($user, $team);
$fake->assertBlocked($user, $team);
$fake->assertConnectedTimes(1);
```

## Public API

| Type      | Class / member                                                            |
|-----------|---------------------------------------------------------------------------|
| Facade    | `RoundlyConsulting\Connections\Facades\Connections` (`::fake()` for tests) |
| Manager   | `RoundlyConsulting\Connections\ConnectionManager`                         |
| Builder   | `RoundlyConsulting\Connections\PendingConnection`                         |
| Actions   | `Actions\CreateConnection`, `DisconnectConnection`, `GrantPermissions`, `RevokePermissions`, `SyncPermissions`, `ExtendConnection`, `PruneConnections`, `AcceptConnection`, `BlockConnection`, `RestoreConnection`, `BulkConnect`, `BulkDisconnect`, `SyncConnections` |
| DTOs      | `DataTransferObjects\ConnectionData`, `PermissionSet`, `SyncResult`, `SyncTarget` |
| Enum      | `Enums\ConnectionStatus` (`Pending`/`Accepted`/`Blocked`)                 |
| Events    | `Events\ConnectionCreated`, `ConnectionUpdated`, `ConnectionRemoved`, `ConnectionPermissionsChanged`, `ConnectionInvited`, `ConnectionAccepted`, `ConnectionBlocked`, `ConnectionRestored`, `ConnectionExpiring` |
| Exceptions| `Exceptions\ConnectionNotFound`, `Exceptions\MissingConnectable`          |
| Commands  | `connections:prune`, `connections:notify-expiring`, `make:connectable`    |
| Model     | `RoundlyConsulting\Connections\Models\Connection`                         |
| Trait     | `RoundlyConsulting\Connections\Concerns\HasConnections`                   |
| Contract  | `RoundlyConsulting\Connections\Contracts\Connectable`                     |
| Testing   | `Testing\ConnectionFake`                                                  |
| Factory   | `RoundlyConsulting\Connections\Database\Factories\ConnectionFactory`      |

> The legacy `RoundlyConsulting\Connections\Connection` model class and
> `RoundlyConsulting\Connections\Interfaces\Connectable` interface remain as
> backward-compatible aliases. New code should use the `Models\` and `Contracts\` names.

## Integrates with

### enums-for-laravel (bundled)

`Enums\ConnectionStatus` uses the shared `RoundlyConsulting\Enums\Helpers` trait, so alongside
its domain guard `canTransitionTo()` it ships the standard enum helpers:

```php
use RoundlyConsulting\Connections\Enums\ConnectionStatus;

ConnectionStatus::values();          // ['pending', 'accepted', 'blocked']
ConnectionStatus::labels();          // ['Pending', 'Accepted', 'Blocked']
ConnectionStatus::toOptions();       // ['pending' => 'Pending', ...] for <select>
ConnectionStatus::options();         // EnumOption DTOs for JS/Inertia selects
ConnectionStatus::validationRule();  // 'in:pending,accepted,blocked'
ConnectionStatus::Accepted->readable();       // 'Accepted'
ConnectionStatus::tryFromLabel('Accepted');   // ConnectionStatus::Accepted
```

`enums-for-laravel` is a runtime dependency and is installed automatically.

### package-toolkit-for-laravel (bundled)

The service provider is built on the shared toolkit: config, the publish-only migration, and the
console commands are declared fluently, `connections.model` is resolved and validated through the
toolkit's model resolver, and the package reports itself in `php artisan about`:

```bash
php artisan about --only=connections
```

The section shows the model, table, default status, whether access checks enforce active links, the
default expiry, how many permissions a new connection starts with (a count, never the ability
names), and whether the gate, cache, and events are on.

`package-toolkit-for-laravel` is a runtime dependency and is installed automatically.

### reports-for-laravel (optional, host-wired)

Connections deliberately does **not** depend on `reports-for-laravel` — the tier DAG keeps
`connections` below its own consumers (`contacts`, `teams`), so it cannot require a higher-tier
package. If you want a "report this connection" flow, wire it in your host app with no change to
this package:

```php
// 1. Install reports in your app:  composer require roundly-consulting/reports-for-laravel
// 2. Point connections at your own model:  config/connections.php => 'model' => \App\Models\Connection::class
// 3. Make that model reportable:
use RoundlyConsulting\Connections\Models\Connection as BaseConnection;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Reports\Traits\HasReports;

class Connection extends BaseConnection implements Reportable
{
    use HasReports;
}

// 4. Report / moderate through the reports facade:
Reports::report($connection)->by($user)->for(Reason::Spam)->create();
$connection->hasBeenReported();      // true
$connection->isReportedBy($user);    // dedupe UX
Connection::query()->mostReported(); // triage worst offenders
```

Multi-moderator sign-off is inherited from `reports → approvals`. Because the report wiring lives
in your app, the connections runtime graph stays acyclic and dependency-free of reports.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=connections-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation or a
monthly pledge on Patreon helps fund maintenance, new features and new packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
