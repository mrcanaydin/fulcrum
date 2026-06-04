<?php

declare(strict_types=1);

namespace Fulcrum\Database\Seeders;

use Fulcrum\Container\Container;
use RuntimeException;

class SeederRunner
{
    public function __construct(private readonly Container $container) {}

    public function run(string $class): string
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Seeder [{$class}] was not found.");
        }

        $seeder = $this->container->make($class);

        if (!$seeder instanceof Seeder) {
            throw new RuntimeException("Seeder [{$class}] must implement " . Seeder::class . '.');
        }

        $seeder->run();

        return $class;
    }
}
