<?php

declare(strict_types=1);

namespace Fulcrum\Database\Grammar;

use Fulcrum\Database\QueryBuilder;

/**
 * Compiles a QueryBuilder into an SQL string.
 */
class SqlGrammar implements GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): string
    {
        $sql = [];

        $sql[] = 'SELECT ' . implode(', ', $builder->components['select']);
        
        if ($builder->components['from']) {
            $sql[] = 'FROM ' . $builder->components['from'];
        }

        if (!empty($builder->components['joins'])) {
            $sql[] = $this->compileJoins($builder->components['joins']);
        }

        if (!empty($builder->components['wheres'])) {
            $sql[] = 'WHERE ' . $this->compileWheres($builder->components['wheres']);
        }

        if (!empty($builder->components['orders'])) {
            $orders = array_map(fn($o) => "{$o['column']} {$o['direction']}", $builder->components['orders']);
            $sql[] = 'ORDER BY ' . implode(', ', $orders);
        }

        if ($builder->components['limit'] !== null) {
            $sql[] = 'LIMIT ' . (int) $builder->components['limit'];
        }

        if ($builder->components['offset'] !== null) {
            $sql[] = 'OFFSET ' . (int) $builder->components['offset'];
        }

        return implode(' ', $sql);
    }

    public function compileInsert(QueryBuilder $builder, array $values): string
    {
        $table = $builder->components['from'];
        $columns = implode(', ', array_keys($values));
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    }

    public function compileUpdate(QueryBuilder $builder, array $values): string
    {
        $table = $builder->components['from'];
        
        $columns = [];
        foreach ($values as $key => $value) {
            $columns[] = "{$key} = ?";
        }
        $columnsStr = implode(', ', $columns);

        $sql = "UPDATE {$table} SET {$columnsStr}";

        if (!empty($builder->components['wheres'])) {
            $sql .= ' WHERE ' . $this->compileWheres($builder->components['wheres']);
        }

        return $sql;
    }

    public function compileDelete(QueryBuilder $builder): string
    {
        $table = $builder->components['from'];
        
        $sql = "DELETE FROM {$table}";

        if (!empty($builder->components['wheres'])) {
            $sql .= ' WHERE ' . $this->compileWheres($builder->components['wheres']);
        }

        return $sql;
    }

    private function compileJoins(array $joins): string
    {
        $sql = [];
        foreach ($joins as $join) {
            $type = strtoupper($join['type']);
            $sql[] = "{$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }
        return implode(' ', $sql);
    }

    private function compileWheres(array $wheres): string
    {
        $sql = [];

        foreach ($wheres as $i => $where) {
            $boolean = $i > 0 ? strtoupper($where['boolean']) . ' ' : '';

            if ($where['type'] === 'Basic') {
                $sql[] = "{$boolean}{$where['column']} {$where['operator']} ?";
            } elseif ($where['type'] === 'In') {
                $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                $sql[] = "{$boolean}{$where['column']} IN ({$placeholders})";
            }
        }

        return implode(' ', $sql);
    }
}
