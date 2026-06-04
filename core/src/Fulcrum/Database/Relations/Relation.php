<?php

declare(strict_types=1);

namespace Fulcrum\Database\Relations;

use Fulcrum\Database\Model;

abstract class Relation
{
    /**
     * @param class-string<Model> $related
     */
    public function __construct(protected readonly string $related) {}

    /** @param Model|array<string, mixed> $model */
    protected function value(Model|array $model, string $key): mixed
    {
        return $model instanceof Model ? $model->getAttribute($key) : ($model[$key] ?? null);
    }
}
