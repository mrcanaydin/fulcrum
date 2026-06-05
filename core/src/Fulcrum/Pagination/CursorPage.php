<?php

declare(strict_types=1);

namespace Fulcrum\Pagination;

final class CursorPage
{
    /**
     * @param list<array<string, mixed>> $nodes
     * @param list<array{cursor: string, node: array<string, mixed>}> $edges
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $edges,
        public readonly bool $hasNextPage,
        public readonly bool $hasPreviousPage,
        public readonly ?string $startCursor,
        public readonly ?string $endCursor,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
            'pageInfo' => [
                'hasNextPage' => $this->hasNextPage,
                'hasPreviousPage' => $this->hasPreviousPage,
                'startCursor' => $this->startCursor,
                'endCursor' => $this->endCursor,
            ],
        ];
    }
}
