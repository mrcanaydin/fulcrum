<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Auth;

use Fulcrum\Auth\AuthServiceProvider;
use Fulcrum\Auth\PermissionManager;
use Fulcrum\Auth\TokenAuthenticator;
use Fulcrum\Auth\TokenManager;
use Fulcrum\Container\Container;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\DatabaseServiceProvider;
use Fulcrum\Foundation\Config;
use Fulcrum\Routing\Request;

function permissionContainer(): Container
{
    $container = new Container();
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:']);
    $container->instance(Config::class, $config);
    (new DatabaseServiceProvider($container))->register();
    (new AuthServiceProvider($container))->register();
    $db = $container->make(DatabaseManager::class);

    $db->connection()->statement('CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, created_at TEXT, updated_at TEXT)');
    $db->connection()->statement('CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, created_at TEXT, updated_at TEXT)');
    $db->connection()->statement('CREATE TABLE role_permissions (permission_id INTEGER, role_id INTEGER, PRIMARY KEY (permission_id, role_id))');
    $db->connection()->statement('CREATE TABLE model_roles (role_id INTEGER, model_type TEXT, model_id TEXT, PRIMARY KEY (role_id, model_type, model_id))');
    $db->connection()->statement('CREATE TABLE model_permissions (permission_id INTEGER, model_type TEXT, model_id TEXT, PRIMARY KEY (permission_id, model_type, model_id))');

    return $container;
}

it('resolves role and direct permissions using application-defined names', function () {
    $permissions = permissionContainer()->make(PermissionManager::class);
    $permissions->createRole('editor');
    $permissions->createPermission('news:create');
    $permissions->createPermission('news:publish');
    $permissions->givePermissionToRole('news:create', 'editor');
    $permissions->assignRole('editor', 'users', '7');
    $permissions->givePermissionToModel('news:publish', 'users', '7');

    expect($permissions->rolesFor('users', '7'))->toBe(['editor'])
        ->and($permissions->permissionsFor('users', '7'))->toContain('news:create', 'news:publish')
        ->and($permissions->hasRole('users', '7', 'editor'))->toBeTrue()
        ->and($permissions->hasPermission('users', '7', 'news:create'))->toBeTrue()
        ->and($permissions->hasPermission('users', '8', 'news:create'))->toBeFalse();
});

it('adds role permissions to authenticated token abilities', function () {
    $container = permissionContainer();
    $db = $container->make(DatabaseManager::class);
    $db->connection()->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, banned_at TEXT NULL)');
    $db->connection()->statement('CREATE TABLE personal_access_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tokenable_type TEXT,
        tokenable_id TEXT,
        name TEXT,
        token TEXT,
        abilities TEXT,
        last_used_at TEXT NULL,
        expires_at TEXT NULL,
        created_at TEXT NULL,
        updated_at TEXT NULL
    )');
    $userId = (string) $db->table('users')->insert(['name' => 'Editor', 'banned_at' => null]);
    $permissions = $container->make(PermissionManager::class);
    $permissions->createRole('admin');
    $permissions->createPermission('*');
    $permissions->givePermissionToRole('*', 'admin');
    $permissions->assignRole('admin', 'users', $userId);
    $token = $container->make(TokenManager::class)->createToken('users', $userId, 'test', ['profile:read']);

    $user = $container->make(TokenAuthenticator::class)->authenticate(
        new Request('POST', '/graphql', ['HTTP_AUTHORIZATION' => 'Bearer ' . $token['accessToken']], [])
    );

    expect($user)->not->toBeNull()
        ->and($user['_roles'])->toBe(['admin'])
        ->and($user['_token']['abilities'])->toContain('profile:read', '*');
});
