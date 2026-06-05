<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        if (!$this->hasIndex($db, 'users_email_unique')) {
            $db->statement('ALTER TABLE users ADD UNIQUE INDEX users_email_unique (email)');
        }
    }

    public function down(ConnectionInterface $db): void
    {
        if ($this->hasIndex($db, 'users_email_unique')) {
            $db->statement('ALTER TABLE users DROP INDEX users_email_unique');
        }
    }

    private function hasIndex(ConnectionInterface $db, string $index): bool
    {
        return $db->select(
            'SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['users', $index]
        )->first() !== null;
    }
};
