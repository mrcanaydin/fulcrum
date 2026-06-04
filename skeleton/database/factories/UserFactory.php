<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Fulcrum\Database\Factories\Factory;

class UserFactory extends Factory
{
    /** @return array{name: string, email: string} */
    protected function definition(): array
    {
        $token = strtolower(bin2hex(random_bytes(4)));

        return [
            'name' => 'Demo User ' . strtoupper($token),
            'email' => "demo.{$token}@example.com",
        ];
    }

    /** @param array{name: string, email: string} $attributes */
    protected function persist(array $attributes): array
    {
        $now = gmdate('Y-m-d H:i:s');

        return User::create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();
    }
}
