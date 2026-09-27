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
- Permission checks with `can()`, `hasAnyPermission()` / `hasAllPermissions()` and wildcard
  matching (`*`, `posts.*`); access requires an active connection by default.
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
- `Connections::fake()` with assertions such as `assertConnected()` and
  `assertHasPermissionThrough()` for your tests.
