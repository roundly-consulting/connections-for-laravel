<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Tests\Fixtures;

use Illuminate\Auth\Access\Response;
use RoundlyConsulting\Connections\Tests\Team;
use RoundlyConsulting\Connections\Tests\User;

/**
 * A host policy with one answer of each kind, to prove which ones the Gate integration may
 * override: a plain deny, a Response deny, an allow, and a method with no opinion (null).
 */
final class TeamPolicy
{
    public function delete(User $user, Team $team): bool
    {
        return false;
    }

    public function transfer(User $user, Team $team): Response
    {
        return Response::deny('Transfers are closed.');
    }

    public function view(User $user, Team $team): bool
    {
        return true;
    }

    public function update(User $user, Team $team): ?bool
    {
        return null;
    }
}
