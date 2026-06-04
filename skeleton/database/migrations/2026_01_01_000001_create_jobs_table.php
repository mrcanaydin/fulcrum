<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement(
            'CREATE TABLE IF NOT EXISTS jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                queue VARCHAR(255) NOT NULL DEFAULT "default",
                payload LONGTEXT NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                reserved_at INT NULL DEFAULT NULL,
                available_at INT NOT NULL,
                created_at INT NOT NULL,
                PRIMARY KEY (id),
                KEY jobs_queue_available_reserved_index (queue, available_at, reserved_at)
            )'
        );
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS jobs');
    }
};
