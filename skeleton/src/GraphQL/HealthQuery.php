<?php

declare(strict_types=1);

namespace App\GraphQL;

use Fulcrum\GraphQL\Attributes\Query;

class HealthQuery
{
    #[Query(name: 'health', type: 'String!')]
    public function health(): string
    {
        return 'ok';
    }
}
