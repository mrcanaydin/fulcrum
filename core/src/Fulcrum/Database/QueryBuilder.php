<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use Fulcrum\Database\Grammar\GrammarInterface;

/**
 * A fluent, chainable, driver-agnostic query builder.
 */
class QueryBuilder
{
    public array $components = [
        'select'  => ['*'],
        'from'    => null,
        'wheres'  => [],
        'joins'   => [],
        'orders'  => [],
        'limit'   => null,
        'offset'  => null,
    ];

    public array $bindings = [
        'select' => [],
        'join'   => [],
        'where'  => [],
    ];

    public function __construct(
        protected ConnectionInterface $connection,
        protected GrammarInterface $grammar
    ) {}

    public function table(string $table): static
    {
        $this->components['from'] = $table;
        return $this;
    }

    public function select(string ...$columns): static
    {
        $this->components['select'] = empty($columns) ? ['*'] : $columns;
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->components['wheres'][] = [
            'type'     => 'Basic',
            'column'   => $column,
            'operator' => $operator,
            'value'    => $value,
            'boolean'  => $boolean,
        ];

        $this->bindings['where'][] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values, string $boolean = 'and'): static
    {
        $this->components['wheres'][] = [
            'type'    => 'In',
            'column'  => $column,
            'values'  => $values,
            'boolean' => $boolean,
        ];

        foreach ($values as $value) {
            $this->bindings['where'][] = $value;
        }

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): static
    {
        $this->components['joins'][] = [
            'table'    => $table,
            'first'    => $first,
            'operator' => $operator,
            'second'   => $second,
            'type'     => $type,
        ];

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->components['orders'][] = [
            'column'    => $column,
            'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc',
        ];
        return $this;
    }

    public function limit(int $value): static
    {
        $this->components['limit'] = $value;
        return $this;
    }

    public function offset(int $value): static
    {
        $this->components['offset'] = $value;
        return $this;
    }

    // ─── Execution Methods ───────────────────────────────────────────────────

    public function get(): \Fulcrum\Support\Collection
    {
        $query = $this->grammar->compileSelect($this);
        return $this->connection->select($query, $this->getBindings());
    }

    public function first(): ?array
    {
        return $this->limit(1)->get()->first();
    }

    public function insert(array $values): int|string
    {
        if (empty($values)) {
            return 0;
        }

        $query = $this->grammar->compileInsert($this, $values);
        $bindings = array_values($values);

        return $this->connection->insert($query, $bindings);
    }

    public function update(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        $query = $this->grammar->compileUpdate($this, $values);
        
        $bindings = array_values($values);
        $bindings = array_merge($bindings, $this->bindings['where']);

        return $this->connection->update($query, $bindings);
    }

    public function delete(): int
    {
        $query = $this->grammar->compileDelete($this);
        return $this->connection->delete($query, $this->bindings['where']);
    }

    /**
     * @return array<mixed>
     */
    public function getBindings(): array
    {
        return array_merge(
            $this->bindings['select'],
            $this->bindings['join'],
            $this->bindings['where']
        );
    }
}
