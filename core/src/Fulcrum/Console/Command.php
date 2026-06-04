<?php

declare(strict_types=1);

namespace Fulcrum\Console;

abstract class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;

    protected string $signature = '';

    protected string $description = '';

    private ?Input $input = null;

    abstract public function handle(): int;

    public function name(): string
    {
        return trim(explode(' ', $this->signature)[0] ?? '');
    }

    public function description(): string
    {
        return $this->description;
    }

    public function setInput(Input $input): void
    {
        $this->input = $input;
    }

    protected function option(string $key, string|bool|null $default = null): string|bool|null
    {
        return $this->input?->option($key, $default) ?? $default;
    }

    protected function stringOption(string $key, string $default = ''): string
    {
        return $this->input?->stringOption($key, $default) ?? $default;
    }

    protected function boolOption(string $key, bool $default = false): bool
    {
        return $this->input?->boolOption($key, $default) ?? $default;
    }

    protected function argument(int $index, string $default = ''): string
    {
        return $this->input?->argument($index, $default) ?? $default;
    }

    protected function line(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}
