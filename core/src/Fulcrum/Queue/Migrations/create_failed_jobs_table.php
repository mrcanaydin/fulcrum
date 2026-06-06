<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;
use Fulcrum\Database\Migrations\SchemaSupport;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $id = SchemaSupport::bigIncrements($db);
        $attempts = SchemaSupport::unsignedInteger($db);
        $integer = SchemaSupport::integer($db);
        $longText = SchemaSupport::isPostgres($db) ? 'TEXT' : 'LONGTEXT';

        $db->statement(
            "CREATE TABLE IF NOT EXISTS failed_jobs (
                id {$id},
                job_id VARCHAR(255) NOT NULL,
                queue VARCHAR(255) NOT NULL,
                payload {$longText} NOT NULL,
                exception {$longText} NOT NULL,
                attempts {$attempts} NOT NULL DEFAULT 0,
                failed_at {$integer} NOT NULL
            )"
        );
        SchemaSupport::createIndex($db, 'failed_jobs', 'failed_jobs_queue_failed_at_index', ['queue', 'failed_at']);
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS failed_jobs');
    }
};
