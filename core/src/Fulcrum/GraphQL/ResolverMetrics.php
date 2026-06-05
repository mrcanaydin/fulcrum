<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Foundation\Config;
use Psr\Log\LoggerInterface;
use Throwable;

final class ResolverMetrics
{
    public function __construct(
        private readonly Config $config,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @template T
     * @param callable(): T $resolver
     * @return T
     */
    public function measure(string $className, string $methodName, RequestContext $context, callable $resolver): mixed
    {
        $startedAt = hrtime(true);
        $status = 'ok';

        try {
            return $resolver();
        } catch (Throwable $exception) {
            $status = 'error';
            throw $exception;
        } finally {
            $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 3);
            $slowThresholdMs = $this->slowThresholdMilliseconds();
            $slow = $slowThresholdMs > 0 && $durationMs >= $slowThresholdMs;
            $record = [
                'resolver' => $className . '::' . $methodName,
                'request_id' => $context->request()->attribute('request_id'),
                'duration_ms' => $durationMs,
                'status' => $status,
                'slow' => $slow,
            ];

            if ($slow) {
                $this->logger?->warning('GraphQL resolver completed slowly.', $record);
            } else {
                $this->logger?->info('GraphQL resolver completed.', $record);
            }
        }
    }

    private function slowThresholdMilliseconds(): float
    {
        $threshold = $this->config->get('graphql.observability.slow_resolver_ms', 100);

        return is_numeric($threshold) ? max(0.0, (float) $threshold) : 100.0;
    }
}
