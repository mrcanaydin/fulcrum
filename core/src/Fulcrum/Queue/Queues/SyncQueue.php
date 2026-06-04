<?php

declare(strict_types=1);

namespace Fulcrum\Queue\Queues;

use Fulcrum\Queue\Job;
use Fulcrum\Queue\JobRunner;
use Fulcrum\Queue\Queue;
use Fulcrum\Queue\QueuedJob;

class SyncQueue implements Queue
{
    public function __construct(private readonly JobRunner $runner) {}

    public function push(Job $job, int $delaySeconds = 0): void
    {
        $this->runner->run($job);
    }

    public function pop(): ?QueuedJob
    {
        return null;
    }

    public function delete(QueuedJob $job): void {}

    public function release(QueuedJob $job, int $delaySeconds = 60): void {}
}
