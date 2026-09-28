<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Connections\DataTransferObjects\PermissionSet;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;

trait ResolvesConfigDefaults
{
    /**
     * Apply config('connections.default_permissions') only when the caller
     * supplied no permission set at all. An explicit (even empty) set wins.
     */
    protected function resolvePermissions(?PermissionSet $permissions): PermissionSet
    {
        if ($permissions !== null) {
            return $permissions;
        }

        $default = config('connections.default_permissions', []);

        if (! is_array($default)) {
            return new PermissionSet;
        }

        return new PermissionSet(array_values(array_map(
            static fn (mixed $permission): string => (string) $permission,
            $default,
        )));
    }

    /**
     * Apply config('connections.expiry.default') only when no expiry was given.
     * Accepts a relative string ("30 days") or seconds — an integer, or a
     * numeric string as it arrives from env().
     */
    protected function resolveExpiry(?CarbonInterface $expiresAt): ?CarbonInterface
    {
        if ($expiresAt !== null) {
            return $expiresAt;
        }

        $default = config('connections.expiry.default');

        if (is_string($default) && ctype_digit(trim($default))) {
            $default = (int) trim($default);
        }

        if (is_int($default)) {
            return Carbon::now()->addSeconds($default);
        }

        if (is_string($default) && trim($default) !== '') {
            return Carbon::now()->add($default);
        }

        return null;
    }

    protected function resolveStatus(?ConnectionStatus $status): ConnectionStatus
    {
        if ($status !== null) {
            return $status;
        }

        $default = config('connections.default_status', ConnectionStatus::Accepted->value);

        if (is_string($default)) {
            return ConnectionStatus::tryFrom($default) ?? ConnectionStatus::Accepted;
        }

        return ConnectionStatus::Accepted;
    }
}
