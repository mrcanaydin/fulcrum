<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Throwable;

class QueueWorker
{
    public function __construct(private readonly QueueManager $queues) {}

    public function work(int $maxJobs = 1, int $sleepSeconds = 1, int $maxAttempts = 3): int
    {
        $processed = 0;

        while ($processed < max(1, $maxJobs)) {
            $job = $this->queues->connection()->pop();

            if (!$job instanceof QueuedJob) {
                sleep(max(0, $sleepSeconds));
                break;
            }

            try {
                $job->job->handle();
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
