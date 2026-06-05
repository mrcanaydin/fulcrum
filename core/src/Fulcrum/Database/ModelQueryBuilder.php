<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use Fulcrum\Pagination\Cursor;
use Fulcrum\Pagination\CursorPage;
use Fulcrum\Pagination\InvalidCursorException;

class ModelQueryBuilder
{
    /** @var list<string> */
    private array $relations = [];

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly QueryBuilder $builder,
    ) {}

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $this->builder->where($column, $operator);
        } else {
            $this->builder->where($column, $operator, $value);
        }

        return $this;
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        $this->builder->whereIn($column, $values);

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->builder->orderBy($column, $direction);

        return $this;
    }

    public function latest(string $column = 'id'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(int $limit): static
    {
        $this->builder->limit($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->builder->offset($offset);

        return $this;
    }

    public function cursorPaginate(
        int $first = 25,
        ?string $after = null,
        ?string $column = null,
        string $direction = 'asc',
        int $maxPageSize = 100,
    ): CursorPage {
        $model = new $this->modelClass();
        $column ??= $model->primaryKey();
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $first = max(1, min($first, max(1, $maxPageSize)));

        if ($after !== null && $after !== '') {
            $cursor = Cursor::decode($after);

            if ($cursor['column'] !== $column) {
                throw new InvalidCursorException('Pagination cursor does not match the requested column.');
            }

            $this->where($column, $direction === 'asc' ? '>' : '<', $cursor['value']);
        }

        $this->orderBy($column, $direction)->limit($first + 1);
        $models = $this->get();
        $hasNextPage = count($models) > $first;
        $models = array_slice($models, 0, $first);
        $nodes = array_map(static fn (Model $item): array => $item->toArray(), $models);
        $edges = [];

        foreach ($nodes as $node) {
            if (!array_key_exists($column, $node)) {
                throw new InvalidCursorException("Cursor column [{$column}] is missing from a paginated result.");
            }

            $edges[] = [
                'cursor' => Cursor::encode($column, $node[$column]),
                'node' => $node,
            ];
        }

        return new CursorPage(
            nodes: $nodes,
            edges: $edges,
            hasNextPage: $hasNextPage,
            hasPreviousPage: $after !== null && $after !== '',
            startCursor: $edges[0]['cursor'] ?? null,
            endCursor: $edges === [] ? null : $edges[array_key_last($edges)]['cursor'],
        );
    }

    public function with(string ...$relations): static
    {
        foreach ($relations as $relation) {
            if ($relation !== '' && !in_array($relation, $this->relations, true)) {
                $this->relations[] = $relation;
            }
        }

        return $this;
    }

    public function find(int|string $id): ?Model
    {
        $model = new $this->modelClass();

        return $this->where($model->primaryKey(), $id)->first();
    }

    public function first(): ?Model
    {
        $row = $this->builder->first();

        $attributes = $this->attributes($row);

        if ($attributes === null) {
            return null;
        }

        $models = [$this->modelClass::hydrate($attributes)];
        $this->eagerLoad($models);

        return $models[0];
    }

    /** @return list<Model> */
    public function get(): array
    {
        $models = [];

        foreach ($this->builder->get()->all() as $row) {
            $attributes = $this->attributes($row);

            if ($attributes !== null) {
                $models[] = $this->modelClass::hydrate($attributes);
            }
        }

        $this->eagerLoad($models);

        return $models;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(
            fn (Model $model): array => $model->toArray(),
            $this->get()
        );
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Model
    {
        $id = $this->builder->insert($attributes);

        return $this->find($id) ?? $this->modelClass::hydrate(array_merge($attributes, ['id' => $id]));
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): int
    {
        return $this->builder->update($attributes);
    }

    public function delete(): int
    {
        return $this->builder->delete();
    }

    /** @return array<string, mixed>|null */
    private function attributes(mixed $row): ?array
    {
        if (!is_array($row)) {
            return null;
        }

        $attributes = [];

        foreach ($row as $key => $value) {
            if (!is_string($key)) {
                return null;
            }

            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /** @param list<Model> $models */
    private function eagerLoad(array $models): void
    {
        if ($models === [] || $this->relations === []) {
            return;
        }

        foreach ($this->relations as $relationName) {
            $prototype = $models[0];

            if (!method_exists($prototype, $relationName)) {
                continue;
            }

            $relation = $prototype->{$relationName}();

            if (!is_object($relation) || !method_exists($relation, 'eagerLoad')) {
                continue;
            }

            $relation->eagerLoad($models, $relationName);
        }
    }
}
