<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement(
            'CREATE TABLE IF NOT EXISTS idempotency_keys (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                scope VARCHAR(64) NOT NULL,
                idempotency_key VARCHAR(255) NOT NULL,
                request_hash VARCHAR(64) NOT NULL,
                response LONGTEXT NOT NULL,
                created_at INT NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY idempotency_keys_scope_key_unique (scope, idempotency_key),
                KEY idempotency_keys_created_at_index (created_at)
            )'
        );
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS idempotency_keys');
    }
};
