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
}
