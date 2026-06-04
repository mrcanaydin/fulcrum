<?php

declare(strict_types=1);

namespace Fulcrum\Queue\Queues;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Queue\Job;
use Fulcrum\Queue\Queue;
use Fulcrum\Queue\QueuedJob;
use RuntimeException;

class DatabaseQueue implements Queue
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly string $table = 'jobs',
        private readonly string $queue = 'default',
    ) {}

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
        $now = time();
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
        $this->db->table($this->table)->where('id', $id)->update([
            'attempts' => $attempts,
            'reserved_at' => $now,
        ]);

        $payload = $row['payload'] ?? '';
        $job = is_string($payload) ? unserialize(base64_decode($payload, true) ?: '', ['allowed_classes' => true]) : null;

        if (!$job instanceof Job) {
            throw new RuntimeException("Queued job [{$id}] payload is invalid.");
        }

        return new QueuedJob($id, $job, $attempts);
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
}
