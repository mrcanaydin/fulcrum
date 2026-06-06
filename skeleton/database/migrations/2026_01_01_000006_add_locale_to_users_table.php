<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Drivers\PostgresDriver;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        if (!$this->hasColumn($db, 'locale')) {
            $db->statement($db instanceof PostgresDriver
                ? 'ALTER TABLE users ADD COLUMN locale VARCHAR(16) NULL DEFAULT NULL'
                : 'ALTER TABLE users ADD COLUMN locale VARCHAR(16) NULL DEFAULT NULL AFTER email');
        }
    }

    public function down(ConnectionInterface $db): void
    {
        if ($this->hasColumn($db, 'locale')) {
            $db->statement('ALTER TABLE users DROP COLUMN locale');
        }
    }

    private function hasColumn(ConnectionInterface $db, string $column): bool
    {
        if ($db instanceof PostgresDriver) {
            return $db->select(
                'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?',
                ['users', $column]
            )->first() !== null;
        }

        return $db->select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['users', $column]
        )->first() !== null;
    }
};
