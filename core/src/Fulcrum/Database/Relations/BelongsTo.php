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
}
