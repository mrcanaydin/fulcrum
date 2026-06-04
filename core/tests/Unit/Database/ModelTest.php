<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Database;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\Model;
use Fulcrum\Foundation\Config;

class ModelTestUser extends Model
{
    protected string $table = 'users';

    public function posts(): \Fulcrum\Database\Relations\HasMany
    {
        return $this->hasMany(ModelTestPost::class, 'user_id');
    }
}

class ModelTestPost extends Model
{
    protected string $table = 'posts';

    public function user(): \Fulcrum\Database\Relations\BelongsTo
    {
        return $this->belongsTo(ModelTestUser::class, 'user_id');
    }
}

function modelTestDatabase(): DatabaseManager
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $db = new DatabaseManager($config);
    Model::resolveDatabaseUsing(fn (): DatabaseManager => $db);
    $db->connection()->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL)');
    $db->connection()->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL)');

    return $db;
}

it('creates, finds, and lists models without raw SQL', function () {
    modelTestDatabase();

    $user = ModelTestUser::create(['name' => 'Ada']);
    $userId = $user->getAttribute('id');

    $found = ModelTestUser::find(is_scalar($userId) ? (string) $userId : '');

    expect($user)->toBeInstanceOf(ModelTestUser::class)
        ->and($user->getAttribute('name'))->toBe('Ada')
        ->and($found?->getAttribute('name'))->toBe('Ada')
        ->and(ModelTestUser::query()->latest()->limit(1)->toArray())->toHaveCount(1);
});

it('resolves has many and belongs to relations', function () {
    modelTestDatabase();

    $user = ModelTestUser::create(['name' => 'Grace']);
    $userId = $user->getAttribute('id');
    ModelTestPost::create(['user_id' => $userId, 'title' => 'Compiler Notes']);
    ModelTestPost::create(['user_id' => $userId, 'title' => 'Debug Diary']);

    $posts = $user instanceof ModelTestUser ? $user->posts()->get($user) : [];
    $post = $posts[0] ?? null;
    $owner = $post instanceof ModelTestPost ? $post->user()->first($post) : null;

    expect($posts)->toHaveCount(2)
        ->and($post?->getAttribute('title'))->toBe('Compiler Notes')
        ->and($owner)->toBeInstanceOf(ModelTestUser::class)
        ->and($owner?->getAttribute('name'))->toBe('Grace');
});

it('eager loads relations for model collections', function () {
    modelTestDatabase();

    $ada = ModelTestUser::create(['name' => 'Ada']);
    $grace = ModelTestUser::create(['name' => 'Grace']);
    ModelTestPost::create(['user_id' => $ada->getAttribute('id'), 'title' => 'Analytical Notes']);
    ModelTestPost::create(['user_id' => $grace->getAttribute('id'), 'title' => 'Compiler Notes']);
    ModelTestPost::create(['user_id' => $grace->getAttribute('id'), 'title' => 'Debug Diary']);

    $users = ModelTestUser::query()->with('posts')->orderBy('id')->get();
    $posts = ModelTestPost::query()->with('user')->orderBy('id')->get();
    $firstPostUser = $posts[0]->toArray()['user'] ?? [];
    $secondPostUser = $posts[1]->toArray()['user'] ?? [];

    expect($users)->toHaveCount(2)
        ->and($users[0]->toArray()['posts'])->toHaveCount(1)
        ->and($users[1]->toArray()['posts'])->toHaveCount(2)
        ->and(is_array($firstPostUser) ? ($firstPostUser['name'] ?? null) : null)->toBe('Ada')
        ->and(is_array($secondPostUser) ? ($secondPostUser['name'] ?? null) : null)->toBe('Grace');
});
