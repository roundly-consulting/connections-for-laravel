<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\DataTransferObjects;

use Illuminate\Support\Collection;

/**
 * An immutable, de-duplicated set of permission strings.
 */
final readonly class PermissionSet
{
    /** @var list<string> */
    public array $permissions;

    /**
     * @param  list<string>  $permissions
     */
    public function __construct(array $permissions = [])
    {
        $this->permissions = array_values(array_unique($permissions));
    }

    public static function make(string ...$permissions): self
    {
        return new self(array_values($permissions));
    }

    /**
     * @param  iterable<int|string, string>  $permissions
     */
    public static function fromIterable(iterable $permissions): self
    {
        $values = [];

        foreach ($permissions as $permission) {
            $values[] = $permission;
        }

        return new self($values);
    }

    public function add(string ...$permissions): self
    {
        return new self([...$this->permissions, ...array_values($permissions)]);
    }

    public function remove(string ...$permissions): self
    {
        return new self(array_values(array_diff($this->permissions, array_values($permissions))));
    }

    public function has(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function isEmpty(): bool
    {
        return $this->permissions === [];
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->permissions;
    }

    /** @return Collection<int, string> */
    public function toCollection(): Collection
    {
        return new Collection($this->permissions);
    }
}
