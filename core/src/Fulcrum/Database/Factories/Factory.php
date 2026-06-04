<?php

declare(strict_types=1);

namespace Fulcrum\Database\Factories;

use BadMethodCallException;

abstract class Factory
{
    private int $count = 1;

    /** @var list<array<string, mixed>|callable(array<string, mixed>, int): array<string, mixed>> */
    private array $states = [];

    /** @return array<string, mixed> */
    abstract protected function definition(): array;

    public function count(int $count): static
    {
        $factory = clone $this;
        $factory->count = max(1, $count);

        return $factory;
    }

    /** @param array<string, mixed>|callable(array<string, mixed>, int): array<string, mixed> $state */
    public function state(array|callable $state): static
    {
        $factory = clone $this;
        $factory->states[] = $state;

        return $factory;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function raw(array $overrides = [], int $index = 0): array
    {
        $attributes = $this->definition();

        foreach ($this->states as $state) {
            $attributes = array_merge(
                $attributes,
                is_callable($state) ? $state($attributes, $index) : $state
            );
        }

        return array_merge($attributes, $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<array<string, mixed>>
     */
    public function make(array $overrides = []): array
    {
        $records = [];

        for ($index = 0; $index < $this->count; $index++) {
            $records[] = $this->raw($overrides, $index);
        }

        return $records;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return list<array<string, mixed>>
     */
    public function create(array $overrides = []): array
    {
        return array_map(
            fn (array $attributes): array => $this->persist($attributes),
            $this->make($overrides)
        );
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    protected function persist(array $attributes): array
    {
        throw new BadMethodCallException('This factory does not define a persist method.');
    }
}
