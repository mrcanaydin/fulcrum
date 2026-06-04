<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

interface Queue
{
    public function push(Job $job, int $delaySeconds = 0): void;

    public function pop(): ?QueuedJob;

    public function delete(QueuedJob $job): void;

    public function release(QueuedJob $job, int $delaySeconds = 60): void;
}
