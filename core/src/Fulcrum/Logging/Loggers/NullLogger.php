<?php

declare(strict_types=1);

namespace Fulcrum\Logging\Loggers;

use Psr\Log\AbstractLogger;
use Stringable;

class NullLogger extends AbstractLogger
{
    /** @param array<string, mixed> $context */
    public function log($level, Stringable|string $message, array $context = []): void {}
}
