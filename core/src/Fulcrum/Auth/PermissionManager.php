<?php

declare(strict_types=1);

namespace Fulcrum\Auth;

use Fulcrum\Database\DatabaseManager;

class PermissionManager
{
    public function __construct(private readonly DatabaseManager $db) {}

    public function createRole(string $name): string
    {
        return (string) $this->db->table('roles')->insert([
            'name' => $name,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function createPermission(string $name): string
    {
        return (string) $this->db->table('permissions')->insert([
            'name' => $name,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function assignRole(string $role, string $modelType, string $modelId): void
    {
        $roleId = $this->roleId($role);
        $this->db->table('model_roles')->insert([
            'role_id' => $roleId,
            'model_type' => $modelType,
            'model_id' => $modelId,
        ]);
    }

    public function givePermissionToRole(string $permission, string $role): void
    {
        $this->db->table('role_permissions')->insert([
            'permission_id' => $this->permissionId($permission),
            'role_id' => $this->roleId($role),
        ]);
    }

    public function givePermissionToModel(string $permission, string $modelType, string $modelId): void
    {
        $this->db->table('model_permissions')->insert([
            'permission_id' => $this->permissionId($permission),
            'model_type' => $modelType,
            'model_id' => $modelId,
        ]);
    }

    /** @return list<string> */
    public function permissionsFor(string $modelType, string $modelId): array
    {
        $direct = $this->db->table('permissions')
            ->select('permissions.name')
            ->join('model_permissions', 'permissions.id', '=', 'model_permissions.permission_id')
            ->where('model_permissions.model_type', $modelType)
            ->where('model_permissions.model_id', $modelId)
            ->get()
            ->pluck('name')
            ->all();
        $throughRoles = $this->db->table('permissions')
            ->select('permissions.name')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->join('model_roles', 'role_permissions.role_id', '=', 'model_roles.role_id')
            ->where('model_roles.model_type', $modelType)
            ->where('model_roles.model_id', $modelId)
            ->get()
            ->pluck('name')
            ->all();

        return $this->stringList([...$direct, ...$throughRoles]);
    }

    /** @return list<string> */
    public function rolesFor(string $modelType, string $modelId): array
    {
        $roles = $this->db->table('roles')
            ->select('roles.name')
            ->join('model_roles', 'roles.id', '=', 'model_roles.role_id')
            ->where('model_roles.model_type', $modelType)
            ->where('model_roles.model_id', $modelId)
            ->get()
            ->pluck('name')
            ->all();

        return $this->stringList($roles);
    }

    public function hasPermission(string $modelType, string $modelId, string $permission): bool
    {
        $permissions = $this->permissionsFor($modelType, $modelId);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function hasRole(string $modelType, string $modelId, string $role): bool
    {
        return in_array($role, $this->rolesFor($modelType, $modelId), true);
    }

    public function revokeRole(string $role, string $modelType, string $modelId): bool
    {
        return $this->db->table('model_roles')
            ->where('role_id', $this->roleId($role))
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->delete() > 0;
    }

    public function revokePermissionFromModel(string $permission, string $modelType, string $modelId): bool
    {
        return $this->db->table('model_permissions')
            ->where('permission_id', $this->permissionId($permission))
            ->where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->delete() > 0;
    }

    private function roleId(string $name): string
    {
        $role = $this->db->table('roles')->where('name', $name)->first();
        $id = is_array($role) ? ($role['id'] ?? null) : null;

        if (!is_scalar($id)) {
            throw new \InvalidArgumentException("Role [{$name}] does not exist.");
        }

        return (string) $id;
    }

    private function permissionId(string $name): string
    {
        $permission = $this->db->table('permissions')->where('name', $name)->first();
        $id = is_array($permission) ? ($permission['id'] ?? null) : null;

        if (!is_scalar($id)) {
            throw new \InvalidArgumentException("Permission [{$name}] does not exist.");
        }

        return (string) $id;
    }

    /** @param array<mixed> $values
     * @return list<string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_unique(array_filter($values, 'is_string')));
    }
}
