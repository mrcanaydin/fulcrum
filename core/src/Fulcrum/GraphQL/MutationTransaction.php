<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Database\DatabaseManager;
use Fulcrum\GraphQL\Exceptions\IdempotencyException;
use JsonException;
use Throwable;

final class MutationTransaction
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function run(callable $callback, ?string $connection = null): mixed
    {
        return $this->db->transaction($callback, $connection);
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function idempotent(
        string $scope,
        string $key,
        string $fingerprint,
        callable $callback,
        ?string $connection = null,
    ): mixed {
        $key = trim($key);

        if ($key === '' || strlen($key) > 255) {
            throw new IdempotencyException('Idempotency-Key must contain between 1 and 255 characters.');
        }

        try {
            return $this->db->transaction(function () use ($scope, $key, $fingerprint, $callback, $connection): mixed {
                $existing = $this->find($scope, $key, $connection);

                if (is_array($existing)) {
                    return $this->replay($existing, $fingerprint);
                }

                $result = $callback();
                $response = $this->encodeResponse($result);

                $this->db->table('idempotency_keys', $connection)->insert([
                    'scope' => $scope,
                    'idempotency_key' => $key,
                    'request_hash' => $fingerprint,
                    'response' => $response,
                    'created_at' => time(),
                ]);

                return $result;
            }, $connection);
        } catch (IdempotencyException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $existing = $this->find($scope, $key, $connection);

            if (is_array($existing)) {
                return $this->replay($existing, $fingerprint);
            }

            throw $exception;
        }
    }

    public function afterCommit(callable $callback, ?string $connection = null): void
    {
        $this->db->afterCommit($callback, $connection);
    }

    private function encodeResponse(mixed $response): string
    {
        try {
            return json_encode($response, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new IdempotencyException(
                'Idempotent mutation responses must be JSON serializable.',
                details: ['reason' => $exception->getMessage()],
            );
        }
    }

    private function decodeResponse(mixed $response): mixed
    {
        if (!is_string($response)) {
            throw new IdempotencyException('Stored idempotency response is invalid.');
        }

        try {
            return json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new IdempotencyException(
                'Stored idempotency response is invalid.',
                details: ['reason' => $exception->getMessage()],
            );
        }
    }

    /** @return array<string, mixed>|null */
    private function find(string $scope, string $key, ?string $connection): ?array
    {
        return $this->db->table('idempotency_keys', $connection)
            ->where('scope', $scope)
            ->where('idempotency_key', $key)
            ->first();
    }

    /** @param array<string, mixed> $record */
    private function replay(array $record, string $fingerprint): mixed
    {
        if (($record['request_hash'] ?? null) !== $fingerprint) {
            throw new IdempotencyException(
                'Idempotency-Key was already used with different mutation arguments.',
                'IDEMPOTENCY_KEY_REUSED',
            );
        }

        return $this->decodeResponse($record['response'] ?? null);
    }
}
