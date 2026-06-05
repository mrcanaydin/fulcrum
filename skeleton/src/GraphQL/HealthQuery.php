<?php

declare(strict_types=1);

namespace App\GraphQL;

use Fulcrum\GraphQL\Attributes\Query;
use Fulcrum\Observability\HealthChecker;

class HealthQuery
{
    public function __construct(private readonly HealthChecker $health) {}

    #[Query(name: 'health', type: 'String!')]
    public function health(): string
    {
        return $this->health->readiness()->healthy ? 'ok' : 'unhealthy';
    }
}
