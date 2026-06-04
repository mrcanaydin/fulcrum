<?php

declare(strict_types=1);

namespace Fulcrum\Console;

class Input
{
    /** @var array<string, string|bool> */
    private array $options = [];

    /** @var list<string> */
    private array $arguments = [];

    /** @param list<string> $tokens */
    public function __construct(array $tokens)
    {
        foreach ($tokens as $token) {
            if (str_starts_with($token, '--')) {
                $option = substr($token, 2);
                [$key, $value] = array_pad(explode('=', $option, 2), 2, true);
                if (is_string($key) && $key !== '') {
                    $this->options[$key] = is_bool($value) || is_string($value) ? $value : true;
                }
                continue;
            }

            $this->arguments[] = $token;
        }
    }

    public function option(string $key, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$key] ?? $default;
    }

    public function stringOption(string $key, string $default = ''): string
    {
        $value = $this->option($key, $default);

        return is_string($value) ? $value : $default;
    }

    public function boolOption(string $key, bool $default = false): bool
    {
        $value = $this->option($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function argument(int $index, string $default = ''): string
    {
        return $this->arguments[$index] ?? $default;
    }
}
