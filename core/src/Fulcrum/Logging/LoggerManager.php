<?php

declare(strict_types=1);

namespace Fulcrum\Logging;

use Fulcrum\Foundation\Config;
use Fulcrum\Logging\Loggers\FileLogger;
use Fulcrum\Logging\Loggers\NullLogger;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class LoggerManager
{
    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    public function __construct(private readonly Config $config) {}

    public function channel(?string $name = null): LoggerInterface
    {
        $name ??= $this->getDefaultChannel();

        if (!isset($this->channels[$name])) {
            $this->channels[$name] = $this->makeChannel($name);
        }

        return $this->channels[$name];
    }

    public function getDefaultChannel(): string
    {
        $default = $this->config->get('logging.default', 'null');

        return is_string($default) && $default !== '' ? $default : 'null';
    }

    public function extend(string $name, LoggerInterface $logger): void
    {
        $this->channels[$name] = $logger;
    }

    private function makeChannel(string $name): LoggerInterface
    {
        $config = $this->config->get("logging.channels.{$name}", ['driver' => $name]);

        if (!is_array($config)) {
            throw new InvalidArgumentException("Log channel [{$name}] is not configured.");
        }

        $driver = $config['driver'] ?? null;

        if (!is_string($driver) || $driver === '') {
            throw new InvalidArgumentException("Log channel [{$name}] requires a driver.");
        }

        return match ($driver) {
            'file' => new FileLogger($this->stringConfig($config, 'path', $this->defaultFilePath())),
            'null' => new NullLogger(),
            default => throw new InvalidArgumentException("Unsupported log driver [{$driver}]."),
        };
    }

    /** @param array<string, mixed> $config */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function defaultFilePath(): string
    {
        $path = $this->config->get('logging.path', getcwd() . '/storage/logs/fulcrum.log');

        return is_string($path) && $path !== '' ? $path : getcwd() . '/storage/logs/fulcrum.log';
    }
}
