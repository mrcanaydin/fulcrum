<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement(
            'CREATE TABLE IF NOT EXISTS failed_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id VARCHAR(255) NOT NULL,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                exception LONGTEXT NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                failed_at INT NOT NULL,
                PRIMARY KEY (id),
                KEY failed_jobs_queue_failed_at_index (queue, failed_at)
            )'
        );
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS failed_jobs');
    }
};
