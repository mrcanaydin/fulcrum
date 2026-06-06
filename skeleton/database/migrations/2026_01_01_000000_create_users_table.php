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
            "CREATE TABLE IF NOT EXISTS users (
                id {$id},
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                locale VARCHAR(16) NULL DEFAULT NULL,
                avatar VARCHAR(2048) NULL DEFAULT NULL,
                gender VARCHAR(32) NULL DEFAULT NULL,
                birthday DATE NULL DEFAULT NULL,
                email_verified_at {$timestamp},
                email_verification_token VARCHAR(128) NULL DEFAULT NULL,
                email_verification_expires_at {$timestamp},
                banned_at {$timestamp},
                ban_reason TEXT NULL DEFAULT NULL,
                created_at {$timestamp},
                updated_at {$timestamp}
            )"
        );
        SchemaSupport::createIndex($db, 'users', 'users_email_unique', ['email'], unique: true);
        SchemaSupport::createIndex($db, 'users', 'users_email_verification_token_index', ['email_verification_token']);
        SchemaSupport::createIndex($db, 'users', 'users_banned_at_index', ['banned_at']);
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS users');
    }
};
