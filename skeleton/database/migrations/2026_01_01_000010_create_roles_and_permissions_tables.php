<?php

declare(strict_types=1);

use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;
use Fulcrum\Database\Migrations\SchemaSupport;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $id = SchemaSupport::bigIncrements($db);
        $bigInteger = SchemaSupport::bigInteger($db);
        $timestamp = SchemaSupport::nullableTimestamp();

        $db->statement("CREATE TABLE IF NOT EXISTS roles (
                id {$id},
                name VARCHAR(255) NOT NULL,
                created_at {$timestamp},
                updated_at {$timestamp}
            )");
        SchemaSupport::createIndex($db, 'roles', 'roles_name_unique', ['name'], unique: true);

        $db->statement("CREATE TABLE IF NOT EXISTS permissions (
                id {$id},
                name VARCHAR(255) NOT NULL,
                created_at {$timestamp},
                updated_at {$timestamp}
            )");
        SchemaSupport::createIndex($db, 'permissions', 'permissions_name_unique', ['name'], unique: true);

        $db->statement("CREATE TABLE IF NOT EXISTS role_permissions (
                permission_id {$bigInteger} NOT NULL,
                role_id {$bigInteger} NOT NULL,
                PRIMARY KEY (permission_id, role_id)
            )");
        SchemaSupport::createIndex($db, 'role_permissions', 'role_permissions_role_id_index', ['role_id']);

        $db->statement("CREATE TABLE IF NOT EXISTS model_roles (
                role_id {$bigInteger} NOT NULL,
                model_type VARCHAR(255) NOT NULL,
                model_id VARCHAR(255) NOT NULL,
                PRIMARY KEY (role_id, model_type, model_id)
            )");
        SchemaSupport::createIndex($db, 'model_roles', 'model_roles_model_index', ['model_type', 'model_id']);

        $db->statement("CREATE TABLE IF NOT EXISTS model_permissions (
                permission_id {$bigInteger} NOT NULL,
                model_type VARCHAR(255) NOT NULL,
                model_id VARCHAR(255) NOT NULL,
                PRIMARY KEY (permission_id, model_type, model_id)
            )");
        SchemaSupport::createIndex($db, 'model_permissions', 'model_permissions_model_index', ['model_type', 'model_id']);
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
