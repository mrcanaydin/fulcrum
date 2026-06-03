<?php

declare(strict_types=1);

use Fulcrum\Foundation\Config;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makeTempConfigDir(array $files): string
{
    $dir = sys_get_temp_dir() . '/fulcrum_config_' . uniqid('', true);
    mkdir($dir, 0777, true);

    foreach ($files as $name => $data) {
        file_put_contents(
            $dir . '/' . $name . '.php',
            '<?php return ' . var_export($data, true) . ';'
        );
    }

    return $dir;
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('Config — loading', function () {
    it('loads values from a PHP config file', function () {
        $dir    = makeTempConfigDir(['app' => ['name' => 'Fulcrum', 'debug' => true]]);
        $config = new Config($dir);

        expect($config->get('app.name'))->toBe('Fulcrum')
            ->and($config->get('app.debug'))->toBeTrue();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    it('loads multiple config files', function () {
        $dir = makeTempConfigDir([
            'app'      => ['name' => 'Fulcrum'],
            'database' => ['host' => 'localhost', 'port' => 5432],
        ]);
        $config = new Config($dir);

        expect($config->get('database.host'))->toBe('localhost')
            ->and($config->get('database.port'))->toBe(5432);

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    it('returns the default when the key does not exist', function () {
        $config = new Config(sys_get_temp_dir() . '/nonexistent_dir_' . uniqid());

        expect($config->get('app.name', 'default'))->toBe('default')
            ->and($config->get('missing'))->toBeNull();
    });

    it('handles a non-existent config directory gracefully', function () {
        $config = new Config('/does/not/exist');
        expect($config->all())->toBe([]);
    });
});

describe('Config — dot notation', function () {
    it('accesses deeply nested keys', function () {
        $dir = makeTempConfigDir(['services' => ['aws' => ['region' => 'us-east-1']]]);
        $config = new Config($dir);

        expect($config->get('services.aws.region'))->toBe('us-east-1');

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });

    it('set() writes via dot notation and get() reads it back', function () {
        $config = new Config(sys_get_temp_dir() . '/nonexistent_' . uniqid());
        $config->set('foo.bar.baz', 42);

        expect($config->get('foo.bar.baz'))->toBe(42);
    });

    it('has() returns true for present keys and false for absent ones', function () {
        $dir    = makeTempConfigDir(['app' => ['name' => 'Fulcrum']]);
        $config = new Config($dir);

        expect($config->has('app.name'))->toBeTrue()
            ->and($config->has('app.missing'))->toBeFalse();

        array_map('unlink', glob($dir . '/*.php') ?: []);
        rmdir($dir);
    });
});
