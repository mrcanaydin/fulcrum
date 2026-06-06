<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;
use Fulcrum\Database\Migrations\SchemaSupport;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $id = SchemaSupport::bigIncrements($db);
        $integer = SchemaSupport::integer($db);
        $response = SchemaSupport::longText($db);

        $db->statement(
            "CREATE TABLE IF NOT EXISTS idempotency_keys (
                id {$id},
                scope VARCHAR(64) NOT NULL,
                idempotency_key VARCHAR(255) NOT NULL,
                request_hash VARCHAR(64) NOT NULL,
                response {$response} NOT NULL,
                created_at {$integer} NOT NULL
            )"
        );
        SchemaSupport::createIndex($db, 'idempotency_keys', 'idempotency_keys_scope_key_unique', ['scope', 'idempotency_key'], unique: true);
        SchemaSupport::createIndex($db, 'idempotency_keys', 'idempotency_keys_created_at_index', ['created_at']);
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS idempotency_keys');
    }
};
