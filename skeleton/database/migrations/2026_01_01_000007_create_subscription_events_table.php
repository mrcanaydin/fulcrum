<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement(
            'CREATE TABLE IF NOT EXISTS subscription_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                topic VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                created_at INT NOT NULL,
                PRIMARY KEY (id),
                KEY subscription_events_topic_id_index (topic, id),
                KEY subscription_events_created_at_index (created_at)
            )'
        );
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS subscription_events');
    }
};
