<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('connections.table');
        $table = is_string($table) ? $table : 'connections';

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->morphs('connector');
            $table->morphs('connectable');
            $table->json('permissions');
            $table->string('status')->default('accepted')->index();
            $table->json('meta')->nullable();
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
