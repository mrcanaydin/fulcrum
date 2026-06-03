<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Database;

use Fulcrum\Database\QueryBuilder;
use Fulcrum\Database\Grammar\SqlGrammar;
use Fulcrum\Database\Grammar\MongoGrammar;
use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Support\Collection;

// Mock connection
class DummyConnection implements ConnectionInterface {
    public function table(string $table): QueryBuilder {
        return new QueryBuilder($this, clone current_grammar());
    }
    public function select(string $query, array $bindings = []): Collection { return new Collection([]); }
    public function insert(string $query, array $bindings = []): int|string { return 1; }
    public function update(string $query, array $bindings = []): int { return 1; }
    public function delete(string $query, array $bindings = []): int { return 1; }
    public function statement(string $query, array $bindings = []): bool { return true; }
}

function sql_builder() {
    return new QueryBuilder(new DummyConnection(), new SqlGrammar());
}

function mongo_builder() {
    return new QueryBuilder(new DummyConnection(), new MongoGrammar());
}

// ─── SQL Grammar Tests ───────────────────────────────────────────────────────

test('SqlGrammar compiles basic select', function () {
    $builder = sql_builder()->table('users')->select('id', 'name')->where('active', true);
    
    $sql = (new SqlGrammar())->compileSelect($builder);
    
    expect($sql)->toBe('SELECT id, name FROM users WHERE active = ?')
        ->and($builder->getBindings())->toBe([true]);
});

test('SqlGrammar compiles select with joins and ordering', function () {
    $builder = sql_builder()->table('posts')
        ->join('users', 'posts.user_id', '=', 'users.id')
        ->whereIn('status', ['published', 'draft'])
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->offset(5);
    
    $sql = (new SqlGrammar())->compileSelect($builder);
    
    expect($sql)->toBe('SELECT * FROM posts INNER JOIN users ON posts.user_id = users.id WHERE status IN (?, ?) ORDER BY created_at desc LIMIT 10 OFFSET 5')
        ->and($builder->getBindings())->toBe(['published', 'draft']);
});

test('SqlGrammar compiles insert', function () {
    $builder = sql_builder()->table('users');
    
    $sql = (new SqlGrammar())->compileInsert($builder, ['name' => 'Alice', 'email' => 'alice@example.com']);
    
    expect($sql)->toBe('INSERT INTO users (name, email) VALUES (?, ?)');
});

test('SqlGrammar compiles update', function () {
    $builder = sql_builder()->table('users')->where('id', 1);
    
    $sql = (new SqlGrammar())->compileUpdate($builder, ['name' => 'Bob']);
    
    expect($sql)->toBe('UPDATE users SET name = ? WHERE id = ?');
});

test('SqlGrammar compiles delete', function () {
    $builder = sql_builder()->table('users')->where('id', 1);
    
    $sql = (new SqlGrammar())->compileDelete($builder);
    
    expect($sql)->toBe('DELETE FROM users WHERE id = ?');
});

// ─── Mongo Grammar Tests ─────────────────────────────────────────────────────

test('MongoGrammar compiles basic select', function () {
    $builder = mongo_builder()->table('users')->select('id', 'name')->where('active', true);
    
    $json = (new MongoGrammar())->compileSelect($builder);
    $command = json_decode($json, true);
    
    expect($command['action'])->toBe('find')
        ->and($command['table'])->toBe('users')
        ->and($command['filter'])->toBe(['active' => '?'])
        ->and($command['options']['projection'])->toBe(['id' => 1, 'name' => 1]);
});

test('MongoGrammar compiles advanced wheres', function () {
    $builder = mongo_builder()->table('posts')
        ->where('views', '>', 100)
        ->whereIn('status', ['published', 'draft']);
    
    $json = (new MongoGrammar())->compileSelect($builder);
    $command = json_decode($json, true);
    
    expect($command['filter']['views'])->toBe(['$gt' => '?'])
        ->and($command['filter']['status'])->toBe(['$in' => ['?', '?']]);
});

test('MongoGrammar compiles insert', function () {
    $builder = mongo_builder()->table('users');
    
    $json = (new MongoGrammar())->compileInsert($builder, ['name' => 'Alice']);
    $command = json_decode($json, true);
    
    expect($command['action'])->toBe('insertOne')
        ->and($command['document'])->toBe(['name' => 'Alice']);
});

test('MongoGrammar compiles update', function () {
    $builder = mongo_builder()->table('users')->where('id', 1);
    
    $json = (new MongoGrammar())->compileUpdate($builder, ['name' => 'Bob']);
    $command = json_decode($json, true);
    
    expect($command['action'])->toBe('updateMany')
        ->and($command['filter'])->toBe(['id' => '?'])
        ->and($command['update']['$set'])->toBe(['name' => 'Bob']);
});

test('MongoGrammar compiles delete', function () {
    $builder = mongo_builder()->table('users')->where('id', 1);
    
    $json = (new MongoGrammar())->compileDelete($builder);
    $command = json_decode($json, true);
    
    expect($command['action'])->toBe('deleteMany')
        ->and($command['filter'])->toBe(['id' => '?']);
});
