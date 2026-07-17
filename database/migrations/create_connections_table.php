<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('connections.table');
        $table = is_string($table) ? $table : 'connections';

        $keyType = KeyType::fromConfig('connections.key_type');

        Schema::create($table, function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('connector', $keyType, nullable: false);
            $table->morphKey('connectable', $keyType, nullable: false);
            // jsonb, not json. On Postgres `json` is stored as text and has no equality
            // operator, so `where permissions = ?` fails outright ("operator does not
            // exist: json = unknown") — which makes assertDatabaseHas() on these columns
            // structurally impossible, and leaves containment unindexable. jsonb stores a
            // parsed representation, supports = and @>, and can carry a GIN index for the
            // whereJsonContains() the permission scopes run.
            //
            // Nothing here depends on json's exact-text fidelity: `permissions` casts to
            // a collection and `meta` to an array, so both are decoded before use and
            // jsonb's key-order/whitespace normalisation is invisible. On SQLite and
            // MySQL the Blueprint compiles jsonb to the same type json did.
            $table->jsonb('permissions');
            $table->string('status')->default('accepted')->index();
            $table->jsonb('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['connector_type', 'connector_id', 'connectable_type', 'connectable_id'],
                'connections_morph_unique',
            );
        });
    }
};
