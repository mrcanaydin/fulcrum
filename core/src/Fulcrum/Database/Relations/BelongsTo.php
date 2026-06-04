<?php

declare(strict_types=1);

namespace Fulcrum\Database\Relations;

use Fulcrum\Database\Model;

class BelongsTo extends Relation
{
    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        string $related,
        private readonly string $foreignKey,
        private readonly string $ownerKey,
    ) {
        parent::__construct($related);
    }

    /** @param Model|array<string, mixed> $child */
    public function first(Model|array $child): ?Model
    {
        return $this->related::query()
            ->where($this->ownerKey, $this->value($child, $this->foreignKey))
            ->first();
    }

    /** @param list<Model> $models */
    public function eagerLoad(array $models, string $relationName): void
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $this->value($model, $this->foreignKey);

            if (is_scalar($key)) {
                $keys[] = $key;
            }
        }

        if ($keys === []) {
            return;
        }

        $related = $this->related::query()
            ->whereIn($this->ownerKey, array_values(array_unique($keys)))
            ->get();

        $dictionary = [];

        foreach ($related as $model) {
            $ownerKey = $model->getAttribute($this->ownerKey);

            if (is_scalar($ownerKey)) {
                $dictionary[(string) $ownerKey] = $model;
            }
        }

        foreach ($models as $model) {
            $key = $this->value($model, $this->foreignKey);
            $model->setRelation($relationName, is_scalar($key) ? ($dictionary[(string) $key] ?? null) : null);
        }
    }
}
