<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

class QueuedJob
{
    public function __construct(
        public readonly string $id,
        public readonly Job $job,
        public readonly int $attempts = 0,
    ) {}
}
