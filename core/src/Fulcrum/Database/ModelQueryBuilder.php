<?php

declare(strict_types=1);

namespace Fulcrum\Database;

class ModelQueryBuilder
{
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

    public function find(int|string $id): ?Model
    {
        $model = new $this->modelClass();

        return $this->where($model->primaryKey(), $id)->first();
    }

    public function first(): ?Model
    {
        $row = $this->builder->first();

        return is_array($row) ? $this->modelClass::hydrate($row) : null;
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
}
