<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Throwable;

interface Queue
{
    public function push(Job $job, int $delaySeconds = 0): void;

    public function pop(): ?QueuedJob;

    public function delete(QueuedJob $job): void;

    public function release(QueuedJob $job, int $delaySeconds = 60): void;

    public function fail(QueuedJob $job, Throwable $exception): void;

    public function size(): int;

    public function failedCount(): int;

    /** @return list<array<string, mixed>> */
    public function failed(?string $id = null): array;

    public function retryFailed(?string $id = null): int;

    public function releaseStale(): int;
}
