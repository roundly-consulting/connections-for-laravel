<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Connections\Database\Factories\ConnectionFactory;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Support\ConnectionsConfig;

/**
 * @property int $id
 * @property int $connector_id
 * @property string $connector_type
 * @property int $connectable_id
 * @property string $connectable_type
 * @property Collection<int, string> $permissions
 * @property ConnectionStatus $status
 * @property array<string, mixed>|null $meta
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    use MassPrunable;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        return ConnectionsConfig::table();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'collection',
            'status' => ConnectionStatus::class,
            'meta' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The MassPrunable hook Laravel's `model:prune` calls (the package's own
     * `connections:prune` goes through PruneConnections, which resolves the seam).
     *
     * `$this->newQuery()` rather than `self::query()`: inside an instance method the two
     * are equivalent — `self::` is a forwarding call, so late static binding still
     * resolves to `$this`'s class and a host's swapped model was already honoured — but
     * `newQuery()` says that plainly instead of routing it through a static call that
     * reads like the seam bypass it is not.
     *
     * Blocked rows are never prunable: `MassPrunable` force-deletes soft-deletable
     * models, so pruning an expired block would erase it and let the pair connect
     * afresh. A block outlives its expiry until accept() lifts it.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return $this->newQuery()
            ->where('expires_at', '<=', now())
            ->where('status', '!=', ConnectionStatus::Blocked->value);
    }

    /** @return MorphTo<Model, $this> */
    public function connector(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function connectable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the connection grants the given permission. A stored '*' grants
     * any permission, and a segment wildcard ('posts.*') matches by pattern.
     */
    public function hasPermission(string $permission): bool
    {
        foreach ($this->permissions as $granted) {
            if (Str::is((string) $granted, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function isPending(): bool
    {
        return $this->status === ConnectionStatus::Pending;
    }

    public function isAccepted(): bool
    {
        return $this->status === ConnectionStatus::Accepted;
    }

    public function isBlocked(): bool
    {
        return $this->status === ConnectionStatus::Blocked;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * A connection is active when it is accepted and not expired. This is the
     * predicate that the "active" scope and access checks key off.
     */
    public function isActive(): bool
    {
        return $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Dot-access a value from the connection's free-form meta bag.
     */
    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta, $key, $default);
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', ConnectionStatus::Accepted->value)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            });
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now());
    }

    /**
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeExpiringSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', Carbon::now())
            ->where('expires_at', '<=', Carbon::now()->addDays($days));
    }

    /**
     * Connections whose stored set grants the permission: the exact string,
     * `*`, or a trailing segment wildcard (`posts.*` and `posts.comments.*`
     * for `posts.comments.delete`). Status-agnostic — chain active() for
     * access semantics. hasPermission() stays the authority for any other
     * pattern shape (e.g. `posts.*.edit`).
     *
     * @param  Builder<Connection>  $query
     * @return Builder<Connection>
     */
    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->where(function (Builder $query) use ($permission): void {
            foreach (self::grantingPatterns($permission) as $pattern) {
                $query->orWhereJsonContains('permissions', $pattern);
            }
        });
    }

    /**
     * @return list<string>
     */
    private static function grantingPatterns(string $permission): array
    {
        $patterns = [$permission, '*'];
        $prefix = '';

        foreach (array_slice(explode('.', $permission), 0, -1) as $segment) {
            $prefix .= $segment.'.';
            $patterns[] = $prefix.'*';
        }

        return array_values(array_unique($patterns));
    }

    protected static function newFactory(): ConnectionFactory
    {
        return ConnectionFactory::new();
    }
}
