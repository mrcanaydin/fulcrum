<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement(
            'CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                locale VARCHAR(16) NULL DEFAULT NULL,
                avatar VARCHAR(2048) NULL DEFAULT NULL,
                gender VARCHAR(32) NULL DEFAULT NULL,
                birthday DATE NULL DEFAULT NULL,
                email_verified_at TIMESTAMP NULL DEFAULT NULL,
                email_verification_token VARCHAR(128) NULL DEFAULT NULL,
                email_verification_expires_at TIMESTAMP NULL DEFAULT NULL,
                banned_at TIMESTAMP NULL DEFAULT NULL,
                ban_reason TEXT NULL DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY users_email_unique (email),
                KEY users_email_verification_token_index (email_verification_token),
                KEY users_banned_at_index (banned_at)
            )'
        );
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS users');
    }
};
