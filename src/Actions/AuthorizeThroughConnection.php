<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Connections\Contracts\Connectable;
use RoundlyConsulting\Connections\Support\ConnectionsConfig;

/**
 * The Gate integration's answer: true when the user's connection to the checked Connectable
 * grants the ability, otherwise null so the Gate's own outcome stands. It never denies.
 *
 * With `gate_wildcards` off only a grant naming the exact ability counts, so a stored `*`
 * or `posts.*` never answers through the Gate (the direct permission checks keep honouring
 * them).
 *
 * @internal Called by the Gate hook the service provider registers.
 */
final readonly class AuthorizeThroughConnection
{
    /**
     * @param  array<array-key, mixed>  $arguments
     */
    public function execute(mixed $user, string $ability, array $arguments): ?bool
    {
        $connectable = $arguments[0] ?? null;

        if (! $user instanceof Model || ! $user instanceof Connectable) {
            return null;
        }

        if (! $connectable instanceof Model || ! $connectable instanceof Connectable) {
            return null;
        }

        if (! $user->hasPermissionThroughConnection($connectable, $ability)) {
            return null;
        }

        if (! ConnectionsConfig::gateHonoursWildcards() && ! $this->grantsExactly($user, $connectable, $ability)) {
            return null;
        }

        return true;
    }

    private function grantsExactly(Connectable $user, Connectable $connectable, string $ability): bool
    {
        // An ability that is itself a pattern could only match a stored pattern.
        if (str_contains($ability, '*')) {
            return false;
        }

        return $user->permissionsThroughConnection($connectable)
            ->contains(static fn (mixed $granted): bool => $granted === $ability);
    }
}
