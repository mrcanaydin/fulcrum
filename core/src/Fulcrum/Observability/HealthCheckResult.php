<?php

declare(strict_types=1);

namespace Fulcrum\Observability;

final class HealthCheckResult
{
    /** @param array<string, array<string, mixed>> $checks */
    public function __construct(
        public readonly bool $healthy,
        public readonly array $checks,
    ) {}

    /** @return array{status: string, checks: array<string, array<string, mixed>>} */
    public function toArray(): array
    {
        return [
            'status' => $this->healthy ? 'ok' : 'unhealthy',
            'checks' => $this->checks,
        ];
    }
}
