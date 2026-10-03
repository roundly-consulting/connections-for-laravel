<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Support\ConnectionsConfig;
use RoundlyConsulting\PackageToolkit\Support\Config;

trait ResolvesConfigDefaults
{
    /**
     * Apply config('connections.default_permissions') only when the caller
     * supplied no permission set at all. An explicit (even empty) set wins; a
     * malformed config list throws.
     */
    protected function resolvePermissions(?PermissionSet $permissions): PermissionSet
    {
        if ($permissions !== null) {
            return $permissions;
        }

        return new PermissionSet(ConnectionsConfig::defaultPermissions());
    }

    /**
     * Apply config('connections.expiry.default') only when no expiry was given.
     * Accepts a relative string ("30 days") or seconds — an integer, or a
     * numeric string as it arrives from env(). Anything else throws.
     */
    protected function resolveExpiry(?CarbonInterface $expiresAt): ?CarbonInterface
    {
        if ($expiresAt !== null) {
            return $expiresAt;
        }

        $default = ConnectionsConfig::defaultExpiry();

        return $default === null ? null : Carbon::now()->add($default);
    }

    /**
     * Apply config('connections.default_status') only when no status was given. Unset
     * means accepted; a value that names no status throws rather than quietly becoming
     * accepted, so a typo cannot turn an invitation flow into live connections.
     */
    protected function resolveStatus(?ConnectionStatus $status): ConnectionStatus
    {
        if ($status !== null) {
            return $status;
        }

        if (config('connections.default_status') === null) {
            return ConnectionStatus::Accepted;
        }

        return Config::enum('connections.default_status', ConnectionStatus::class);
    }
}
