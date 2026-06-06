<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;
use Fulcrum\Database\Migrations\SchemaSupport;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $id = SchemaSupport::bigIncrements($db);
        $timestamp = SchemaSupport::nullableTimestamp();

        $db->statement(
            "CREATE TABLE IF NOT EXISTS personal_access_tokens (
                id {$id},
                tokenable_type VARCHAR(255) NOT NULL,
                tokenable_id VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                token VARCHAR(64) NOT NULL,
                abilities TEXT NULL,
                last_used_at {$timestamp},
                expires_at {$timestamp},
                created_at {$timestamp},
                updated_at {$timestamp}
            )"
        );
        SchemaSupport::createIndex($db, 'personal_access_tokens', 'personal_access_tokens_token_unique', ['token'], unique: true);
        SchemaSupport::createIndex($db, 'personal_access_tokens', 'personal_access_tokens_tokenable_index', ['tokenable_type', 'tokenable_id']);
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS personal_access_tokens');
    }
};
