<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Drivers\PostgresDriver;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        if (!$this->hasIndex($db, 'users_email_unique')) {
            $db->statement($db instanceof PostgresDriver
                ? 'CREATE UNIQUE INDEX users_email_unique ON users (email)'
                : 'ALTER TABLE users ADD UNIQUE INDEX users_email_unique (email)');
        }
    }

    public function down(ConnectionInterface $db): void
    {
        if ($this->hasIndex($db, 'users_email_unique')) {
            $db->statement($db instanceof PostgresDriver
                ? 'ALTER TABLE users DROP CONSTRAINT users_email_unique'
                : 'ALTER TABLE users DROP INDEX users_email_unique');
        }
    }

    private function hasIndex(ConnectionInterface $db, string $index): bool
    {
        if ($db instanceof PostgresDriver) {
            return $db->select(
                'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                ['users', $index]
            )->first() !== null;
        }

        return $db->select(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['users', $index]
        )->first() !== null;
    }
};
