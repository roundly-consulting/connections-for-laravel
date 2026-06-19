# Changelog

All notable changes to `connections-for-laravel` will be documented in this file.

## 1.1.0 - unreleased

### Added

- **Invitations / status lifecycle** — connections now have a `status`
  (`pending`/`accepted`/`blocked`) via the `ConnectionStatus` enum, with
  `invite()`/`accept()`/`block()` on the builder, `acceptConnectionFrom()` /
  `blockConnectionFrom()` / `inviteConnection()` on the trait, the `AcceptConnection` /
  `BlockConnection` actions, and `ConnectionInvited` / `ConnectionAccepted` /
  `ConnectionBlocked` events.
- **Metadata** — a nullable JSON `meta` column, `withMeta()` / `replaceMeta()` on the builder,
  a `meta('dot.key')` accessor, and a `meta` argument on `connectTo()`.
- **Bulk operations** — `toMany()` with `connectAll()` / `disconnectAll()` / `grantAll()` /
  `revokeAll()`, plus `syncConnections()` reconciling an exact set and returning a typed
  `SyncResult` (`BulkConnect` / `BulkDisconnect` / `SyncConnections` actions, `SyncTarget` DTO).
- **Permission ergonomics** — `hasAnyPermission()` / `hasAllPermissions()`, wildcard `*` and
  segment (`posts.*`) matching in permission checks, and `clearPermissions()`.
- **Query scopes & typed fetches** — `active` / `expired` / `expiringSoon` / `withPermission`
  scopes surfaced as `activeConnections()` / `expiredConnections()` / `expiringConnections()` /
  `connectionsWithPermission()`, plus `connectablesOfType()` / `connectorsOfType()`.
- **DX** — `toggle()` / `reconnect()` / `restore()` (and `RestoreConnection` action +
  `ConnectionRestored` event), the `make:connectable` generator, the
  `connections:notify-expiring` command + `ConnectionExpiring` event, config defaults
  (`default_permissions`, `default_status`, `expiry.default`), and `Connections::fake()` with
  assertion helpers (`ConnectionFake`).

### Changed

- **Behaviour change:** access checks (`hasPermissionThroughConnection`, the opt-in Gate) and
  `isConnectedTo` now require a connection to be **active** (accepted and not expired) by
  default. Previously an expired connection still granted permission. Controlled by the new
  `connections.enforce_active_on_check` config flag; set it to `false` to restore the prior
  behaviour. This is the only behavioural change — everything else is additive.
