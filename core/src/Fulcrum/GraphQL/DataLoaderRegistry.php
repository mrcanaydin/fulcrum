<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

class DataLoaderRegistry
{
    /** @var array<string, DataLoader> */
    private array $loaders = [];

    /** @param callable(list<mixed>): array<string, mixed> $batchLoad */
    public function register(string $name, callable $batchLoad): DataLoader
    {
        return $this->loaders[$name] = new DataLoader($batchLoad);
    }

    public function get(string $name): ?DataLoader
    {
        return $this->loaders[$name] ?? null;
    }

    /** @param callable(list<mixed>): array<string, mixed> $batchLoad */
    public function getOrRegister(string $name, callable $batchLoad): DataLoader
    {
        return $this->loaders[$name] ??= new DataLoader($batchLoad);
    }
}
