<?php

declare(strict_types=1);

namespace Fulcrum\Schedule;

class ScheduledCommand
{
    private int $intervalSeconds = 60;

    public function __construct(private readonly string $command) {}

    public function command(): string
    {
        return $this->command;
    }

    public function everyMinute(): static
    {
        $this->intervalSeconds = 60;

        return $this;
    }

    public function everyFiveMinutes(): static
    {
        $this->intervalSeconds = 300;

        return $this;
    }

    public function hourly(): static
    {
        $this->intervalSeconds = 3600;

        return $this;
    }

    public function daily(): static
    {
        $this->intervalSeconds = 86400;

        return $this;
    }

    public function everySeconds(int $seconds): static
    {
        $this->intervalSeconds = max(1, $seconds);

        return $this;
    }

    public function isDue(int $now): bool
    {
        return $now % $this->intervalSeconds === 0;
    }
}
