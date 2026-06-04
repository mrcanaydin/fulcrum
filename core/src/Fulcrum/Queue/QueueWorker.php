<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Fulcrum\Container\Container;
use Throwable;

class QueueWorker
{
    public function __construct(
        private readonly QueueManager $queues,
        ?JobRunner $runner = null,
    ) {
        $this->runner = $runner ?? new JobRunner(new Container());
    }

    private readonly JobRunner $runner;

    public function work(int $maxJobs = 0, int $sleepSeconds = 1, int $maxAttempts = 3): int
    {
        $processed = 0;

        while ($maxJobs <= 0 || $processed < $maxJobs) {
            $job = $this->queues->connection()->pop();

            if (!$job instanceof QueuedJob) {
                sleep(max(0, $sleepSeconds));

                if ($maxJobs > 0) {
                    break;
                }

                continue;
            }

            try {
                $this->runner->run($job->job);
                $this->queues->connection()->delete($job);
            } catch (Throwable $e) {
                if ($job->attempts >= $maxAttempts) {
                    $this->queues->connection()->delete($job);
                    throw $e;
                }

                $this->queues->connection()->release($job);
            }

            $processed++;
        }

        return $processed;
    }
}
