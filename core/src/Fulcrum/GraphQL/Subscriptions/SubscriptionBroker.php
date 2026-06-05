<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL\Subscriptions;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\Foundation\Config;

class SubscriptionBroker
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly Config $config,
    ) {}

    /** @param array<string, mixed> $payload */
    public function publish(string $topic, array $payload): string
    {
        $retention = $this->config->get('subscriptions.retention_seconds', 86400);
        $this->db->table($this->table())
            ->where('created_at', '<', time() - (is_numeric($retention) ? max(60, (int) $retention) : 86400))
            ->delete();

        $id = $this->db->table($this->table())->insert([
            'topic' => $topic,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => time(),
        ]);

        return (string) $id;
    }

    /** @return list<array{id: string, topic: string, payload: array<string, mixed>}> */
    public function events(string $topic, string $after = '0', int $limit = 100): array
    {
        $rows = $this->db->table($this->table())
            ->where('topic', $topic)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->all();
        $events = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            /** @var array<string, mixed> $row */
            $payload = json_decode(is_string($row['payload'] ?? null) ? $row['payload'] : '{}', true);
            $id = $row['id'] ?? '';
            $eventTopic = $row['topic'] ?? $topic;
            $events[] = [
                'id' => is_scalar($id) ? (string) $id : '',
                'topic' => is_scalar($eventTopic) ? (string) $eventTopic : $topic,
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        return $events;
    }

    private function table(): string
    {
        $table = $this->config->get('subscriptions.table', 'subscription_events');

        return is_string($table) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) ? $table : 'subscription_events';
    }
}
