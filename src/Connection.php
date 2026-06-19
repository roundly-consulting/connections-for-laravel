<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use RoundlyConsulting\Connections\Database\Factories\ConnectionFactory;

/**
 * @property int $id
 * @property int $connector_id
 * @property string $connector_type
 * @property int $connectable_id
 * @property string $connectable_type
 * @property Collection<int, string> $permissions
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    use MassPrunable;
    use SoftDeletes;

    /** @var list<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'permissions' => 'collection',
            'expires_at' => 'datetime',
        ];
    }

    /** @return Builder<Connection> */
    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<=', now());
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

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains($permission);
    }

    protected static function newFactory(): ConnectionFactory
    {
        return ConnectionFactory::new();
    }
}
