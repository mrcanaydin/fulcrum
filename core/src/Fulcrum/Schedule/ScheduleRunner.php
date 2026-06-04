<?php

declare(strict_types=1);

namespace Fulcrum\Schedule;

use Fulcrum\Console\Application as ConsoleApplication;
use Fulcrum\Foundation\Application as Kernel;

class ScheduleRunner
{
    public function __construct(private readonly Kernel $kernel) {}

    /**
     * @param list<ScheduledCommand> $events
     * @return list<string>
     */
    public function run(array $events, ?int $now = null): array
    {
        $now ??= time();
        $ran = [];

        foreach ($events as $event) {
            if (!$event->isDue($now)) {
                continue;
            }

            $tokens = preg_split('/\s+/', trim($event->command())) ?: [];
            $argv = array_merge(['fulcrum'], array_values(array_filter($tokens, fn (string $token): bool => $token !== '')));
            (new ConsoleApplication($this->kernel))->run($argv);
            $ran[] = $event->command();
        }

        return $ran;
    }
}
