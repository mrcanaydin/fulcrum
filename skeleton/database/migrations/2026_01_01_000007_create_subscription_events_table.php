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
        $payload = SchemaSupport::longText($db);

        $db->statement(
            "CREATE TABLE IF NOT EXISTS subscription_events (
                id {$id},
                topic VARCHAR(255) NOT NULL,
                payload {$payload} NOT NULL,
                created_at {$integer} NOT NULL
            )"
        );
        SchemaSupport::createIndex($db, 'subscription_events', 'subscription_events_topic_id_index', ['topic', 'id']);
        SchemaSupport::createIndex($db, 'subscription_events', 'subscription_events_created_at_index', ['created_at']);
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS subscription_events');
    }
};
