# Changelog

All notable changes to `connections-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Many-to-many connections between any Eloquent models via the `HasConnections` trait and the
  `Connectable` contract, with per-connection permissions and optional expiry.
- A fluent `Connections` facade —
  `Connections::between($user, $team)->withPermissions(...)->connect()` — plus trait verbs such as
  `connectTo()`, `grantThroughConnection()` and `disconnectFrom()`.
- A `permissions()` sub-accessor on every pair —
  `Connections::between($a, $b)->permissions()->grant()/revoke()/sync()/clear()/all()/has()/hasAny()/hasAll()` —
  with wildcard matching (`*`, `posts.*`); access requires an active connection by default.
- `Connections::between($a, $b)->find()` (the pair's connection in any status),
  `Connections::from($user)->sync([...])` (reconcile to an exact set),
  `Connections::expiring($days)` (every connection expiring soon) and `Connections::flushCache()`.
- Invitation flows: `invite()` creates a pending connection that the other side accepts or
  blocks (`acceptConnectionFrom()`, `blockConnectionFrom()`), tracked by `ConnectionStatus`.
- Free-form JSON metadata on each connection (`withMeta()`, `meta('dot.key')`).
- Bulk operations: `toMany()` with `connectAll()` / `grantAll()` / `revokeAll()`, and
  `syncConnections()` returning a typed `SyncResult`.
- `toggle()`, `reconnect()` and `restore()` for soft-deleted connections, plus query scopes and
  typed fetches (`activeConnections()`, `expiringConnections()`, `connectionsWithPermission()`).
- Lifecycle actions and events (`ConnectionCreated`, `ConnectionInvited`, `ConnectionAccepted`,
  `ConnectionPermissionsChanged`, `ConnectionExpiring`, …) for every write.
- An opt-in `Gate::before` integration (`connections.register_gate`) so
  `$user->can('publish', $team)` authorizes through connections.
- Artisan commands: `connections:prune`, `connections:notify-expiring` and the
  `make:connectable` model generator.
- `Connections::fake()` — a recording fake that also captures injected-manager, sub-accessor and
  `HasConnections` trait calls, with an `assert*` / `assertNothing*` pair for every write
  (`assertConnected()`, `assertDisconnected()`, `assertGranted()`, `assertRevoked()`,
  `assertPermissionsSynced()`, `assertSynced()`, `assertExtended()`, `assertReconnected()`,
  `assertPruned()`, …).

### Changed

- The `Connections` facade resolves `ConnectionManager::class`; the `'connections'` container
  alias is gone — inject or `app(ConnectionManager::class)`.
- Pair permission verbs moved under `permissions()`: `between()->grant/revoke/sync/clearPermissions/can`
  became `between()->permissions()->grant/revoke/sync/clear/has`. `between()->sync()` now means
  "reconcile connections" (the old `syncConnections()` use case); the staged-permission fallback of
  the old `grant()` is gone — pass the permissions.
- `syncConnections()` / `from()->sync()` take `Connectable` or `SyncTarget` items only; the
  `['model' => …, 'permissions' => …]` array form is removed (pass a `SyncTarget`).
- `HasConnections` writes delegate to `ConnectionManager` instead of calling actions, so the fake
  and host overrides see them. `Cache` is `@internal` — use `Connections::flushCache()`.
