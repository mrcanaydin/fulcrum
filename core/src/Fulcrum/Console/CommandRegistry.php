<?php

declare(strict_types=1);

namespace Fulcrum\Console;

use Fulcrum\Container\Contracts\ContainerInterface;
use RuntimeException;

class CommandRegistry
{
    /** @param list<class-string> $commands */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly array $commands,
    ) {}

    public function has(string $name): bool
    {
        return $this->resolve($name) !== null;
    }

    /** @param list<string> $tokens */
    public function run(string $name, array $tokens = []): int
    {
        $command = $this->resolve($name);

        if (!$command instanceof Command) {
            throw new RuntimeException("Command [{$name}] is not registered.");
        }

        $command->setInput(new Input($tokens));

        return $command->handle();
    }

    /** @return list<Command> */
    public function all(): array
    {
        $commands = [];

        foreach ($this->commands as $class) {
            $command = $this->container->get($class);

            if ($command instanceof Command) {
                $commands[] = $command;
            }
        }

        return $commands;
    }

    private function resolve(string $name): ?Command
    {
        foreach ($this->all() as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        return null;
    }
}
