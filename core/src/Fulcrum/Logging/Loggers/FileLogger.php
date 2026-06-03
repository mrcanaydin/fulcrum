<?php

declare(strict_types=1);

namespace Fulcrum\Logging\Loggers;

use Psr\Log\AbstractLogger;
use Stringable;

class FileLogger extends AbstractLogger
{
    public function __construct(private readonly string $path)
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }
    }

    /** @param array<string, mixed> $context */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $record = [
            'timestamp' => gmdate('c'),
            'level' => $this->normaliseLevel($level),
            'message' => $this->interpolate((string) $message, $context),
            'context' => $this->normaliseContext($context),
        ];

        @file_put_contents(
            $this->path,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function normaliseLevel(mixed $level): string
    {
        if (is_string($level) || is_int($level) || is_float($level) || $level instanceof Stringable) {
            return (string) $level;
        }

        return 'debug';
    }

    /** @param array<string, mixed> $context */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null || $value instanceof Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normaliseContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                ];
            }
        }

        return $context;
    }
}
