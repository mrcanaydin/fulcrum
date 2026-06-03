<?php

declare(strict_types=1);

namespace Fulcrum\Support;

/**
 * Immutable, chainable collection.
 *
 * Wraps an array and provides a fluent interface for transformations.
 * All transformation methods return a new Collection instance, keeping
 * the original unchanged.
 *
 * @template T
 */
class Collection implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @param T[] $items */
    public function __construct(private array $items = []) {}

    /** @param T[] $items */
    public static function make(array $items = []): static
    {
        return new static($items);
    }

    // ─── Transformations ─────────────────────────────────────────────────────

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): static
    {
        $filtered = $callback === null
            ? array_filter($this->items)
            : array_filter($this->items, $callback);

        return new static(array_values($filtered));
    }

    public function reject(callable $callback): static
    {
        return $this->filter(fn ($item) => !$callback($item));
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function flatMap(callable $callback): static
    {
        $result = [];
        foreach ($this->items as $item) {
            $mapped = $callback($item);
            foreach ((is_array($mapped) ? $mapped : [$mapped]) as $value) {
                $result[] = $value;
            }
        }
        return new static($result);
    }

    public function each(callable $callback): static
    {
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }
        return $this;
    }

    public function sortBy(callable $callback): static
    {
        $items = $this->items;
        usort($items, $callback);
        return new static($items);
    }

    public function unique(): static
    {
        return new static(array_values(array_unique($this->items)));
    }

    public function reverse(): static
    {
        return new static(array_values(array_reverse($this->items)));
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return new static(array_values(array_slice($this->items, $offset, $length)));
    }

    public function take(int $limit): static
    {
        return $this->slice(0, $limit);
    }

    public function skip(int $count): static
    {
        return $this->slice($count);
    }

    public function merge(array|self $items): static
    {
        $toMerge = $items instanceof self ? $items->all() : $items;
        return new static(array_merge($this->items, $toMerge));
    }

    public function pluck(string $key): static
    {
        return new static(array_column($this->items, $key));
    }

    public function keyBy(string $key): static
    {
        $result = [];
        foreach ($this->items as $item) {
            if (is_array($item) && isset($item[$key])) {
                $result[$item[$key]] = $item;
            } elseif (is_object($item) && isset($item->$key)) {
                $result[$item->$key] = $item;
            }
        }
        return new static($result);
    }

    // ─── Searching ───────────────────────────────────────────────────────────

    public function first(?callable $callback = null): mixed
    {
        if ($callback === null) {
            $key = array_key_first($this->items);
            return $key === null ? null : $this->items[$key];
        }
        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }
        return null;
    }

    public function last(?callable $callback = null): mixed
    {
        $items = $callback ? $this->filter($callback)->all() : $this->items;
        return empty($items) ? null : $items[array_key_last($items)];
    }

    public function contains(mixed $value): bool
    {
        if (is_callable($value)) {
            foreach ($this->items as $item) {
                if ($value($item)) {
                    return true;
                }
            }
            return false;
        }
        return in_array($value, $this->items, strict: true);
    }

    // ─── Aggregates ──────────────────────────────────────────────────────────

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function sum(?string $key = null): int|float
    {
        $values = $key !== null ? $this->pluck($key)->all() : $this->items;
        return array_sum($values);
    }

    // ─── Output ──────────────────────────────────────────────────────────────

    /** @return T[] */
    public function all(): array
    {
        return $this->items;
    }

    /** @return T[] */
    public function toArray(): array
    {
        return $this->items;
    }

    public function toJson(): string
    {
        return json_encode($this->items, JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    // ─── Mutation (append-only to remain chainable) ──────────────────────────

    public function push(mixed $value): static
    {
        return new static([...$this->items, $value]);
    }

    public function prepend(mixed $value): static
    {
        return new static([$value, ...$this->items]);
    }

    // ─── ArrayAccess ─────────────────────────────────────────────────────────

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // ─── IteratorAggregate ───────────────────────────────────────────────────

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }
}
