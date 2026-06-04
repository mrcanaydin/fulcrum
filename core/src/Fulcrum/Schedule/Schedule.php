<?php

declare(strict_types=1);

namespace Fulcrum\Schedule;

class Schedule
{
    public static function command(string $command): ScheduledCommand
    {
        return new ScheduledCommand($command);
    }
}
