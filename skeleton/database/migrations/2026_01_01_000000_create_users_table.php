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
                created_at TIMESTAMP NULL DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY users_email_unique (email)
            )'
        );
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS users');
    }
};
