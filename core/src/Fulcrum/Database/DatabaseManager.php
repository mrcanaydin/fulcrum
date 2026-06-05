<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use PDO;
use InvalidArgumentException;
use MongoDB\Client as MongoClient;
use Fulcrum\Foundation\Config;
use Fulcrum\Database\Drivers\MysqlDriver;
use Fulcrum\Database\Drivers\PostgresDriver;
use Fulcrum\Database\Drivers\MongoDriver;

/**
 * Factory and manager for database connections.
 */
class DatabaseManager
{
    /** @var array<string, ConnectionInterface> */
    protected array $connections = [];

    public function __construct(
        protected Config $config
    ) {}

    public function connection(?string $name = null): ConnectionInterface
    {
        $name = $name ?: $this->getDefaultConnection();

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->makeConnection($name);
        }

        return $this->connections[$name];
    }

    protected function makeConnection(string $name): ConnectionInterface
    {
        $config = $this->config->get("database.connections.{$name}");

        if (!$config) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }

        return match ($config['driver']) {
            'mysql'  => $this->createMysqlConnection($config),
            'pgsql'  => $this->createPostgresConnection($config),
            'sqlite' => $this->createSqliteConnection($config),
            'mongo'  => $this->createMongoConnection($config),
            default  => throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]."),
        };
    }

    protected function createSqliteConnection(array $config): MysqlDriver
    {
        // Using MysqlDriver as base PDO driver wrapper, works perfectly for SQLite
        $dsn = "sqlite:{$config['database']}";
        $pdo = new PDO($dsn, null, null, $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return new MysqlDriver($pdo, $config['prefix'] ?? '');
    }

    protected function createMysqlConnection(array $config): MysqlDriver
    {
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        if (isset($config['charset'])) {
            $dsn .= ";charset={$config['charset']}";
        }

        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return new MysqlDriver($pdo, $config['prefix'] ?? '');
    }

    protected function createPostgresConnection(array $config): PostgresDriver
    {
        $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
        
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return new PostgresDriver($pdo, $config['prefix'] ?? '');
    }

    protected function createMongoConnection(array $config): MongoDriver
    {
        $dsn = $config['dsn'] ?? "mongodb://{$config['host']}:{$config['port']}";
        
        $options = [];
        if (isset($config['username']) && isset($config['password'])) {
            $options['username'] = $config['username'];
            $options['password'] = $config['password'];
        }

        $client = new MongoClient($dsn, $options);

        return new MongoDriver($client, $config['database']);
    }

    public function getDefaultConnection(): string
    {
        return $this->config->get('database.default', 'mysql');
    }

    /**
     * Start a fluent query builder on the default connection.
     */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return $this->connection($connection)->table($table);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        return $this->connection($connection)->transaction($callback);
    }

    public function afterCommit(callable $callback, ?string $connection = null): void
    {
        $this->connection($connection)->afterCommit($callback);
    }
}
