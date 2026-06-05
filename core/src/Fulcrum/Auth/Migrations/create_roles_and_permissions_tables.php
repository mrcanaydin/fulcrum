<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement('CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY roles_name_unique (name)
        )');
        $db->statement('CREATE TABLE IF NOT EXISTS permissions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY permissions_name_unique (name)
        )');
        $db->statement('CREATE TABLE IF NOT EXISTS role_permissions (
            permission_id BIGINT UNSIGNED NOT NULL,
            role_id BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (permission_id, role_id),
            KEY role_permissions_role_id_index (role_id)
        )');
        $db->statement('CREATE TABLE IF NOT EXISTS model_roles (
            role_id BIGINT UNSIGNED NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            PRIMARY KEY (role_id, model_type, model_id),
            KEY model_roles_model_index (model_type, model_id)
        )');
        $db->statement('CREATE TABLE IF NOT EXISTS model_permissions (
            permission_id BIGINT UNSIGNED NOT NULL,
            model_type VARCHAR(255) NOT NULL,
            model_id VARCHAR(255) NOT NULL,
            PRIMARY KEY (permission_id, model_type, model_id),
            KEY model_permissions_model_index (model_type, model_id)
        )');
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE IF EXISTS model_permissions');
        $db->statement('DROP TABLE IF EXISTS model_roles');
        $db->statement('DROP TABLE IF EXISTS role_permissions');
        $db->statement('DROP TABLE IF EXISTS permissions');
        $db->statement('DROP TABLE IF EXISTS roles');
    }
};
