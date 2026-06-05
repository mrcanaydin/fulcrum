<?php

declare(strict_types=1);

namespace Fulcrum\Auth\Models;

use Fulcrum\Database\DatabaseManager;

/**
 * Basic model for the personal_access_tokens table.
 */
class PersonalAccessToken
{
    public const TABLE = 'personal_access_tokens';

    public function __construct(
        protected DatabaseManager $db
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): string
    {
        return (string) $this->db->table(self::TABLE)->insert($attributes);
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        return $this->db->table(self::TABLE)
            ->where('id', $id)
            ->first();
    }

    public function touch(string $id): void
    {
        $this->db->table(self::TABLE)
            ->where('id', $id)
            ->update(['last_used_at' => gmdate('Y-m-d H:i:s')]);
    }

    public function delete(string $id): int
    {
        return $this->db->table(self::TABLE)
            ->where('id', $id)
            ->delete();
    }

    public function deleteForUser(string $id, string $tokenableType, string $tokenableId): int
    {
        return $this->db->table(self::TABLE)
            ->where('id', $id)
            ->where('tokenable_type', $tokenableType)
            ->where('tokenable_id', $tokenableId)
            ->delete();
    }

    public function deleteAllForUser(string $tokenableType, string $tokenableId): int
    {
        return $this->db->table(self::TABLE)
            ->where('tokenable_type', $tokenableType)
            ->where('tokenable_id', $tokenableId)
            ->delete();
    }
}
