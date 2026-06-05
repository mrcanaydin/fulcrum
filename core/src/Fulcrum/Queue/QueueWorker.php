<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Fulcrum\Container\Container;
use Psr\Log\LoggerInterface;
use Throwable;

class QueueWorker
{
    private bool $shouldStop = false;

    public function __construct(
        private readonly QueueManager $queues,
        ?JobRunner $runner = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->runner = $runner ?? new JobRunner(new Container());
    }

    private readonly JobRunner $runner;

    public function work(
        int $maxJobs = 0,
        int $sleepSeconds = 1,
        int $maxAttempts = 3,
        int $timeoutSeconds = 0,
        int $backoffSeconds = 5,
        int $maxBackoffSeconds = 300,
    ): int
    {
        $processed = 0;
        $this->shouldStop = false;
        $this->listenForSignals();

        while (!$this->shouldStop && ($maxJobs <= 0 || $processed < $maxJobs)) {
            $queue = $this->queues->connection();
            $job = $queue->pop();

            if (!$job instanceof QueuedJob) {
                sleep(max(0, $sleepSeconds));

                if ($maxJobs > 0) {
                    break;
                }

                continue;
            }

            $startedAt = hrtime(true);

            try {
                $this->runWithTimeout($job->job, $timeoutSeconds);
                $queue->delete($job);
                $this->logger?->info('Queue job completed.', $this->metrics($job, $startedAt));
            } catch (Throwable $e) {
                if ($job->attempts >= $maxAttempts) {
                    $queue->fail($job, $e);
                    $this->logger?->error('Queue job failed permanently.', $this->metrics($job, $startedAt) + [
                        'exception' => $e,
                    ]);
                } else {
                    $delay = $this->backoff($job->attempts, $backoffSeconds, $maxBackoffSeconds);
                    $queue->release($job, $delay);
                    $this->logger?->warning('Queue job released for retry.', $this->metrics($job, $startedAt) + [
                        'exception' => $e,
                        'backoff_seconds' => $delay,
                    ]);
                }
            }

            $processed++;
        }

        return $processed;
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function runWithTimeout(Job $job, int $timeoutSeconds): void
    {
        if ($timeoutSeconds <= 0 || !function_exists('pcntl_signal') || !function_exists('pcntl_alarm')) {
            $this->runner->run($job);

            return;
        }

        $previousHandler = function_exists('pcntl_signal_get_handler')
            ? pcntl_signal_get_handler(SIGALRM)
            : SIG_DFL;

        pcntl_signal(SIGALRM, static function () use ($timeoutSeconds): void {
            throw new JobTimeoutException($timeoutSeconds);
        });
        pcntl_alarm($timeoutSeconds);

        try {
            $this->runner->run($job);
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler);
        }
    }

    private function listenForSignals(): void
    {
        if (!function_exists('pcntl_async_signals') || !function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn (): bool => $this->shouldStop = true);
        pcntl_signal(SIGINT, fn (): bool => $this->shouldStop = true);
    }

    private function backoff(int $attempt, int $baseSeconds, int $maximumSeconds): int
    {
        $baseSeconds = max(0, $baseSeconds);
        $maximumSeconds = max($baseSeconds, $maximumSeconds);

        return min($maximumSeconds, $baseSeconds * (2 ** max(0, $attempt - 1)));
    }

    /** @return array<string, int|float|string> */
    private function metrics(QueuedJob $job, int $startedAt): array
    {
        $metrics = $this->queues->metrics();

        return [
            'job_id' => $job->id,
            'job' => $job->job::class,
            'queue' => $job->queue,
            'attempts' => $job->attempts,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
            'queue_depth' => $metrics['pending'],
            'failed_jobs' => $metrics['failed'],
        ];
    }
}
