<?php

declare(strict_types=1);

namespace Fulcrum\Tests\Unit\Database;

use Fulcrum\Container\Container;
use Fulcrum\Database\Factories\Factory;
use Fulcrum\Database\Factories\FactoryCreator;
use Fulcrum\Database\Seeders\Seeder;
use Fulcrum\Database\Seeders\SeederCreator;
use Fulcrum\Database\Seeders\SeederRunner;

function seederFactoryTestPath(string $prefix): string
{
    $path = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    mkdir($path, 0777, true);

    return $path;
}

it('runs seeders through the container', function () {
    $container = new Container();
    $GLOBALS['fulcrum_test_seeder_ran'] = false;
    $seederClass = get_class(new class implements Seeder {
        public function run(): void
        {
            $GLOBALS['fulcrum_test_seeder_ran'] = true;
        }
    });

    expect((new SeederRunner($container))->run($seederClass))->toBe($seederClass)
        ->and($GLOBALS['fulcrum_test_seeder_ran'])->toBeTrue();
});

it('creates seeder files from safe names', function () {
    $path = seederFactoryTestPath('fulcrum-seeders');
    $file = (new SeederCreator())->create($path, 'user');

    expect($file)->toEndWith('/UserSeeder.php')
        ->and(file_exists($file))->toBeTrue();

    unlink($file);
    rmdir($path);
});

it('builds factory records with count, state, and overrides', function () {
    $factory = new class extends Factory {
        /** @return array{name: string, active: bool} */
        protected function definition(): array
        {
            return [
                'name' => 'Demo',
                'active' => true,
            ];
        }
    };

    $records = $factory
        ->count(2)
        ->state(function (array $attributes, int $index): array {
            $name = is_string($attributes['name'] ?? null) ? $attributes['name'] : '';

            return ['name' => $name . ' ' . ($index + 1)];
        })
        ->make(['active' => false]);

    expect($records)->toBe([
        ['name' => 'Demo 1', 'active' => false],
        ['name' => 'Demo 2', 'active' => false],
    ]);
});

it('creates factory files from safe names', function () {
    $path = seederFactoryTestPath('fulcrum-factories');
    $file = (new FactoryCreator())->create($path, 'user');

    expect($file)->toEndWith('/UserFactory.php')
        ->and(file_exists($file))->toBeTrue();

    unlink($file);
    rmdir($path);
});
