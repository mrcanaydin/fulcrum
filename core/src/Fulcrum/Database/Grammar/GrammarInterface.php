<?php

declare(strict_types=1);

namespace Fulcrum\Database\Grammar;

use Fulcrum\Database\QueryBuilder;

interface GrammarInterface
{
    public function compileSelect(QueryBuilder $builder): string;
    public function compileInsert(QueryBuilder $builder, array $values): string;
    public function compileUpdate(QueryBuilder $builder, array $values): string;
    public function compileDelete(QueryBuilder $builder): string;
}
