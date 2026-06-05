<?php

declare(strict_types=1);

namespace Fulcrum\Queue\Queues;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Queue\Job;
use Fulcrum\Queue\Queue;
use Fulcrum\Queue\QueuedJob;
use RuntimeException;
use Throwable;

class DatabaseQueue implements Queue
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly string $table = 'jobs',
        private readonly string $queue = 'default',
        private readonly string $failedTable = 'failed_jobs',
        private readonly int $retryAfterSeconds = 90,
    ) {
        $this->validateIdentifier($this->table);
        $this->validateIdentifier($this->failedTable);
    }

    public function push(Job $job, int $delaySeconds = 0): void
    {
        $now = time();

        $this->db->table($this->table)->insert([
            'queue' => $this->queue,
            'payload' => base64_encode(serialize($job)),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now + max(0, $delaySeconds),
            'created_at' => $now,
        ]);
    }

    public function pop(): ?QueuedJob
    {
        $this->releaseStale();
        $now = time();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $row = $this->db->table($this->table)
                ->where('queue', $this->queue)
                ->where('available_at', '<=', $now)
                ->whereNull('reserved_at')
                ->orderBy('id')
                ->first();

            if (!is_array($row)) {
                return null;
            }

            $id = (string) ($row['id'] ?? '');
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $claimed = $this->db->table($this->table)
                ->where('id', $id)
                ->whereNull('reserved_at')
                ->update([
                    'attempts' => $attempts,
                    'reserved_at' => $now,
                ]);

            if ($claimed !== 1) {
                continue;
            }

            return new QueuedJob($id, $this->decodeJob($row['payload'] ?? null, $id), $attempts, $this->queue);
        }

        return null;
    }

    public function delete(QueuedJob $job): void
    {
        $this->db->table($this->table)->where('id', $job->id)->delete();
    }

    public function release(QueuedJob $job, int $delaySeconds = 60): void
    {
        $this->db->table($this->table)->where('id', $job->id)->update([
            'reserved_at' => null,
            'available_at' => time() + max(0, $delaySeconds),
        ]);
    }

    public function fail(QueuedJob $job, Throwable $exception): void
    {
        $this->db->transaction(function () use ($job, $exception): void {
            $this->db->table($this->failedTable)->insert([
                'job_id' => $job->id,
                'queue' => $job->queue,
                'payload' => base64_encode(serialize($job->job)),
                'exception' => $exception::class . ': ' . $exception->getMessage(),
                'attempts' => $job->attempts,
                'failed_at' => time(),
            ]);
            $this->delete($job);
        });
    }

    public function size(): int
    {
        return $this->countRows($this->table, 'queue', $this->queue);
    }

    public function failedCount(): int
    {
        return $this->countRows($this->failedTable, 'queue', $this->queue);
    }

    public function failed(?string $id = null): array
    {
        $query = $this->db->table($this->failedTable)->where('queue', $this->queue);
        if ($id !== null && $id !== '') {
            $query->where('id', $id);
        }

        $failed = [];

        foreach ($query->orderBy('id', 'desc')->get()->all() as $row) {
            if (!is_array($row)) {
                continue;
            }

            $record = [];
            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $record[$key] = $value;
                }
            }
            $failed[] = $record;
        }

        return $failed;
    }

    public function retryFailed(?string $id = null): int
    {
        $failed = array_reverse($this->failed($id));
        $retried = 0;

        foreach ($failed as $row) {
            if (!is_array($row)) {
                continue;
            }

            $failedId = isset($row['id']) && is_scalar($row['id']) ? (string) $row['id'] : '';
            $payload = $row['payload'] ?? null;
            $this->decodeJob($payload, $failedId);

            $this->db->transaction(function () use ($row, $failedId): void {
                $this->db->table($this->table)->insert([
                    'queue' => $this->queue,
                    'payload' => $row['payload'],
                    'attempts' => 0,
                    'reserved_at' => null,
                    'available_at' => time(),
                    'created_at' => time(),
                ]);
                $this->db->table($this->failedTable)->where('id', $failedId)->delete();
            });
            $retried++;
        }

        return $retried;
    }

    public function releaseStale(): int
    {
        if ($this->retryAfterSeconds <= 0) {
            return 0;
        }

        return $this->db->connection()->update(
            "UPDATE {$this->table} SET reserved_at = NULL WHERE queue = ? AND reserved_at IS NOT NULL AND reserved_at <= ?",
            [$this->queue, time() - $this->retryAfterSeconds],
        );
    }

    private function decodeJob(mixed $payload, string $id): Job
    {
        $job = is_string($payload) ? unserialize(base64_decode($payload, true) ?: '', ['allowed_classes' => true]) : null;

        if (!$job instanceof Job) {
            throw new RuntimeException("Queued job [{$id}] payload is invalid.");
        }

        return $job;
    }

    private function countRows(string $table, string $column, string $value): int
    {
        $row = $this->db->connection()->select(
            "SELECT COUNT(*) AS aggregate FROM {$table} WHERE {$column} = ?",
            [$value],
        )->first();

        return is_array($row) && is_numeric($row['aggregate'] ?? null) ? (int) $row['aggregate'] : 0;
    }

    private function validateIdentifier(string $identifier): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new RuntimeException("Invalid queue table identifier [{$identifier}].");
        }
    }
}
