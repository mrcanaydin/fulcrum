<?php

declare(strict_types=1);

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Database\Migrations\MigrationCreator;
use Fulcrum\Database\Migrations\MigrationRepository;
use Fulcrum\Database\Migrations\Migrator;
use Fulcrum\Foundation\Config;

function migrationTestDatabase(): DatabaseManager
{
    $config = new Config(__DIR__ . '/missing');
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    return new DatabaseManager($config);
}

function migrationTestPath(): string
{
    $path = sys_get_temp_dir() . '/fulcrum-migrations-' . bin2hex(random_bytes(6));
    mkdir($path, 0777, true);

    return $path;
}

it('runs pending migrations and rolls back the last batch', function () {
    $db = migrationTestDatabase();
    $path = migrationTestPath();

    file_put_contents($path . '/2026_01_01_000000_create_widgets.php', <<<'PHP'
<?php
use Fulcrum\Database\ConnectionInterface;
use Fulcrum\Database\Migrations\Migration;

return new class implements Migration {
    public function up(ConnectionInterface $db): void
    {
        $db->statement('CREATE TABLE widgets (id INTEGER PRIMARY KEY, name VARCHAR(255) NOT NULL)');
    }

    public function down(ConnectionInterface $db): void
    {
        $db->statement('DROP TABLE widgets');
    }
};
PHP);

    $migrator = new Migrator(new MigrationRepository($db));

    expect($migrator->run($path))->toBe(['2026_01_01_000000_create_widgets'])
        ->and($migrator->run($path))->toBe([])
        ->and($db->connection()->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'widgets'")->first())
        ->not->toBeNull()
        ->and($migrator->rollback($path))->toBe(['2026_01_01_000000_create_widgets'])
        ->and($db->connection()->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'widgets'")->first())
        ->toBeNull();

    unlink($path . '/2026_01_01_000000_create_widgets.php');
    rmdir($path);
});

it('creates migration files from safe names', function () {
    $path = migrationTestPath();
    $file = (new MigrationCreator())->create($path, 'create_api_tokens');

    expect($file)->toEndWith('_create_api_tokens.php')
        ->and(file_exists($file))->toBeTrue()
        ->and(require $file)->toBeInstanceOf(\Fulcrum\Database\Migrations\Migration::class);

    unlink($file);
    rmdir($path);
});
