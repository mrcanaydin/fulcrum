<?php

declare(strict_types=1);

namespace Fulcrum\Console;

use Fulcrum\Container\ServiceProvider;
use Fulcrum\Foundation\Config;

class ConsoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(CommandRegistry::class, function ($container): CommandRegistry {
            $config = $container->make(Config::class);
            $commands = $config instanceof Config ? $config->get('console.commands', []) : [];

            $classes = [];

            if (is_array($commands)) {
                foreach ($commands as $command) {
                    if (is_string($command) && class_exists($command)) {
                        $classes[] = $command;
                    }
                }
            }

            return new CommandRegistry($container, $classes);
        });
    }
}
