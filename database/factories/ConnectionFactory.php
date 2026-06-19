<?php

declare(strict_types=1);

namespace RoundlyConsulting\Connections\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Connections\Enums\ConnectionStatus;
use RoundlyConsulting\Connections\Models\Connection;

/** @extends Factory<Connection> */
final class ConnectionFactory extends Factory
{
    protected $model = Connection::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'connector_id' => $this->faker->randomNumber(),
            'connector_type' => 'connector',
            'connectable_id' => $this->faker->randomNumber(),
            'connectable_type' => 'connectable',
            'permissions' => collect(),
            'status' => ConnectionStatus::Accepted,
            'meta' => null,
            'expires_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn (): array => [
            'status' => ConnectionStatus::Pending,
        ]);
    }

    public function blocked(): self
    {
        return $this->state(fn (): array => [
            'status' => ConnectionStatus::Blocked,
        ]);
    }
}
