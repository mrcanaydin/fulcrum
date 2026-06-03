<?php

declare(strict_types=1);

namespace Fulcrum\Database\Grammar;

use Fulcrum\Database\QueryBuilder;

/**
 * Compiles a QueryBuilder into a JSON string representing a MongoDB command.
 * The MongoDriver will decode this JSON and execute it.
 */
class MongoGrammar implements GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): string
    {
        $command = [
            'action' => 'find',
            'table'  => $builder->components['from'],
            'filter' => $this->compileWheres($builder->components['wheres']),
            'options' => [],
        ];

        if ($builder->components['select'] !== ['*']) {
            $projection = [];
            foreach ($builder->components['select'] as $column) {
                $projection[$column] = 1;
            }
            $command['options']['projection'] = $projection;
        }

        if (!empty($builder->components['orders'])) {
            $sort = [];
            foreach ($builder->components['orders'] as $order) {
                $sort[$order['column']] = strtolower($order['direction']) === 'asc' ? 1 : -1;
            }
            $command['options']['sort'] = $sort;
        }

        if ($builder->components['limit'] !== null) {
            $command['options']['limit'] = (int) $builder->components['limit'];
        }

        if ($builder->components['offset'] !== null) {
            $command['options']['skip'] = (int) $builder->components['offset'];
        }

        return json_encode($command);
    }

    public function compileInsert(QueryBuilder $builder, array $values): string
    {
        $command = [
            'action'   => 'insertOne',
            'table'    => $builder->components['from'],
            'document' => $values,
        ];

        return json_encode($command);
    }

    public function compileUpdate(QueryBuilder $builder, array $values): string
    {
        $command = [
            'action' => 'updateMany',
            'table'  => $builder->components['from'],
            'filter' => $this->compileWheres($builder->components['wheres']),
            'update' => ['$set' => $values],
        ];

        return json_encode($command);
    }

    public function compileDelete(QueryBuilder $builder): string
    {
        $command = [
            'action' => 'deleteMany',
            'table'  => $builder->components['from'],
            'filter' => $this->compileWheres($builder->components['wheres']),
        ];

        return json_encode($command);
    }

    private function compileWheres(array $wheres): array
    {
        // For Mongo, we merge all wheres into a single filter array.
        // We handle simple bindings by replacing '?' sequentially in the driver,
        // or we can build the filter map here and assume the bindings array matches exactly.
        // To simplify, we will just use placeholders '?' here and let the driver replace them.
        
        $filter = [];
        foreach ($wheres as $where) {
            if ($where['type'] === 'Basic') {
                $op = match($where['operator']) {
                    '='  => '$eq',
                    '!=' => '$ne',
                    '>'  => '$gt',
                    '>=' => '$gte',
                    '<'  => '$lt',
                    '<=' => '$lte',
                    default => '$eq',
                };
                
                if ($op === '$eq') {
                     $filter[$where['column']] = '?';
                } else {
                     $filter[$where['column']] = [$op => '?'];
                }
            } elseif ($where['type'] === 'In') {
                $filter[$where['column']] = ['$in' => array_fill(0, count($where['values']), '?')];
            }
        }

        return $filter;
    }
}
