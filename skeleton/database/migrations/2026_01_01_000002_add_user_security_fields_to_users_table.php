<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Drivers\PostgresDriver;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $this->addColumn($db, 'avatar', 'VARCHAR(2048) NULL DEFAULT NULL');
        $this->addColumn($db, 'gender', 'VARCHAR(32) NULL DEFAULT NULL');
        $this->addColumn($db, 'birthday', 'DATE NULL DEFAULT NULL');
        $this->addColumn($db, 'email_verified_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumn($db, 'email_verification_token', 'VARCHAR(128) NULL DEFAULT NULL');
        $this->addColumn($db, 'email_verification_expires_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumn($db, 'banned_at', 'TIMESTAMP NULL DEFAULT NULL');
        $this->addColumn($db, 'ban_reason', 'TEXT NULL DEFAULT NULL');
    }

    public function down(ConnectionInterface $db): void
    {
        $this->dropColumn($db, 'ban_reason');
        $this->dropColumn($db, 'banned_at');
        $this->dropColumn($db, 'email_verification_expires_at');
        $this->dropColumn($db, 'email_verification_token');
        $this->dropColumn($db, 'email_verified_at');
        $this->dropColumn($db, 'birthday');
        $this->dropColumn($db, 'gender');
        $this->dropColumn($db, 'avatar');
    }

    private function addColumn(ConnectionInterface $db, string $column, string $definition): void
    {
        if (!$this->hasColumn($db, $column)) {
            $db->statement("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    }

    private function dropColumn(ConnectionInterface $db, string $column): void
    {
        if ($this->hasColumn($db, $column)) {
            $db->statement("ALTER TABLE users DROP COLUMN {$column}");
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
