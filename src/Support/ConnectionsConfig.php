<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Support;

use Carbon\CarbonInterval;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use Throwable;

/**
 * The strict readers behind the connections settings the toolkit has no single reader for.
 *
 * A key that is not set (absent, null or blank: `''` or whitespace, a host's `KEY=`) means
 * the documented default. A present value of the wrong shape throws
 * InvalidConfigurationException naming the key: a typo never falls back silently.
 * Before, a default expiry that was neither a string nor an int meant "never expires", a
 * non-array permission list granted nothing, and a non-string table became `connections`.
 *
 * @internal
 */
final class ConnectionsConfig
{
    private const string EXPIRY = 'connections.expiry.default';

    private const string PERMISSIONS = 'connections.default_permissions';

    /**
     * The connections table: `connections` when not set, otherwise a string.
     */
    public static function table(): string
    {
        return self::isUnset(config('connections.table')) ? 'connections' : Config::requireString('connections.table');
    }

    /**
     * The permissions a new connection gets when the caller supplies none: an empty list when
     * not set, otherwise a list of non-empty permission names (a blank name in the list is junk,
     * not an unset key, and throws).
     *
     * @return list<string>
     */
    public static function defaultPermissions(): array
    {
        $permissions = config(self::PERMISSIONS);

        if (self::isUnset($permissions)) {
            return [];
        }

        if (! is_array($permissions)) {
            throw self::mustBe(self::PERMISSIONS, 'a list of permission names', $permissions);
        }

        $names = [];

        foreach ($permissions as $permission) {
            if (! is_string($permission) || trim($permission) === '') {
                throw self::mustBe(self::PERMISSIONS, 'a list of non-empty permission names', $permission);
            }

            $names[] = $permission;
        }

        return $names;
    }

    /**
     * The default lifetime of a new connection, or null when connections never expire by
     * default (not set: absent, null or blank).
     *
     * A whole number of seconds (an int, or an integer string as env() hands it over) must be
     * at least 1; any other string must be a positive relative interval such as `30 days`.
     * Anything else throws.
     */
    public static function defaultExpiry(): ?CarbonInterval
    {
        $value = config(self::EXPIRY);

        if (self::isUnset($value)) {
            return null;
        }

        if (is_int($value) || (is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value) === 1)) {
            return CarbonInterval::seconds(Config::integer(self::EXPIRY, 1, min: 1));
        }

        try {
            $interval = is_string($value) ? CarbonInterval::make($value) : null;
        } catch (Throwable) {
            $interval = null;
        }

        if ($interval === null || $interval->totalSeconds <= 0) {
            throw self::mustBe(self::EXPIRY, 'a positive interval such as "30 days" or a number of seconds', $value);
        }

        return $interval;
    }

    /**
     * Not set: null or blank (`''` or whitespace, a host's `KEY=`), read exactly like absent.
     */
    private static function isUnset(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function mustBe(string $key, string $expectation, mixed $value): InvalidConfigurationException
    {
        $given = match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new InvalidConfigurationException("Configuration value [{$key}] must be {$expectation}, [{$given}] given.");
    }
}
