<?php

declare(strict_types=1);

namespace Fulcrum\Database\Relations;

use Fulcrum\Database\Model;

class HasMany extends Relation
{
    /**
     * @param class-string<Model> $related
     */
    public function __construct(
        string $related,
        private readonly string $foreignKey,
        private readonly string $localKey,
    ) {
        parent::__construct($related);
    }

    /**
     * @param Model|array<string, mixed> $parent
     * @return list<Model>
     */
    public function get(Model|array $parent): array
    {
        return $this->related::query()
            ->where($this->foreignKey, $this->value($parent, $this->localKey))
            ->get();
    }

    /** @param list<Model> $models */
    public function eagerLoad(array $models, string $relationName): void
    {
        $keys = [];

        foreach ($models as $model) {
            $key = $this->value($model, $this->localKey);

            if (is_scalar($key)) {
                $keys[] = $key;
            }
        }

        if ($keys === []) {
            return;
        }

        $related = $this->related::query()
            ->whereIn($this->foreignKey, array_values(array_unique($keys)))
            ->get();

        $groups = [];

        foreach ($related as $model) {
            $foreignKey = $model->getAttribute($this->foreignKey);

            if (is_scalar($foreignKey)) {
                $groups[(string) $foreignKey][] = $model;
            }
        }

        foreach ($models as $model) {
            $key = $this->value($model, $this->localKey);
            $model->setRelation($relationName, is_scalar($key) ? ($groups[(string) $key] ?? []) : []);
        }
    }
}
