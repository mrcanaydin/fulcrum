<?php

declare(strict_types=1);

namespace App\Models;

use Fulcrum\Database\DatabaseManager;

class User
{
    public function __construct(private readonly DatabaseManager $db) {}

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->db->table('users')
            ->where('id', $id)
            ->first();
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 25): array
    {
        return $this->db->table('users')
            ->select('id', 'name', 'email', 'created_at', 'updated_at')
            ->orderBy('id', 'desc')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->all();
    }

    /**
     * @param array{name: string, email: string} $attributes
     * @return array<string, mixed>
     */
    public function create(array $attributes): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $id = $this->db->table('users')->insert([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find((string) $id) ?? [
            'id' => (string) $id,
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
