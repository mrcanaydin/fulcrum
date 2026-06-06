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
        $defaultQueue = SchemaSupport::isPostgres($db) ? "'default'" : '"default"';
        $payload = SchemaSupport::longText($db);

        $db->statement(
            "CREATE TABLE IF NOT EXISTS jobs (
                id {$id},
                queue VARCHAR(255) NOT NULL DEFAULT {$defaultQueue},
                payload {$payload} NOT NULL,
                attempts {$attempts} NOT NULL DEFAULT 0,
                reserved_at {$integer} NULL DEFAULT NULL,
                available_at {$integer} NOT NULL,
                created_at {$integer} NOT NULL
            )"
        );
        SchemaSupport::createIndex($db, 'jobs', 'jobs_queue_available_reserved_index', ['queue', 'available_at', 'reserved_at']);
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS jobs');
    }
};
