<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use RuntimeException;

final class JobTimeoutException extends RuntimeException
{
    public function __construct(int $seconds)
    {
        parent::__construct("Queued job exceeded its {$seconds} second timeout.");
    }
}
