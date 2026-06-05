<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        if (!$this->hasColumn($db, 'password_hash')) {
            $db->statement('ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email');
        }
    }

    public function down(ConnectionInterface $db): void
    {
        if ($this->hasColumn($db, 'password_hash')) {
            $db->statement('ALTER TABLE users DROP COLUMN password_hash');
        }
    }

    private function hasColumn(ConnectionInterface $db, string $column): bool
    {
        return $db->select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['users', $column]
        )->first() !== null;
    }
};
