<?php

declare(strict_types=1);

namespace Fulcrum\Mail;

use Fulcrum\Foundation\Config;
use Fulcrum\Mail\Transports\LogTransport;
use Fulcrum\Mail\Transports\SmtpTransport;
use InvalidArgumentException;

class MailManager
{
    /** @var array<string, MailTransport> */
    private array $mailers = [];

    public function __construct(private readonly Config $config) {}

    public function mailer(?string $name = null): MailTransport
    {
        $name ??= $this->defaultMailer();

        return $this->mailers[$name] ??= $this->make($name);
    }

    public function send(Message $message, ?string $mailer = null): void
    {
        $this->mailer($mailer)->send($message);
    }

    public function defaultMailer(): string
    {
        $default = $this->config->get('mail.default', 'log');

        return is_string($default) && $default !== '' ? $default : 'log';
    }

    private function make(string $name): MailTransport
    {
        $config = $this->config->get("mail.mailers.{$name}", ['transport' => $name]);

        if (!is_array($config)) {
            throw new InvalidArgumentException("Mailer [{$name}] is not configured.");
        }

        $transport = $config['transport'] ?? $name;

        if (!is_string($transport)) {
            throw new InvalidArgumentException("Mailer [{$name}] requires a string transport.");
        }

        return match ($transport) {
            'log' => new LogTransport($this->stringConfig($config, 'path', getcwd() . '/storage/logs/mail.log')),
            'smtp' => new SmtpTransport(
                $this->stringConfig($config, 'host', '127.0.0.1'),
                $this->intConfig($config, 'port', 587),
                $this->nullableStringConfig($config, 'username'),
                $this->nullableStringConfig($config, 'password'),
                $this->stringConfig($config, 'encryption', 'tls'),
                $this->stringConfig($config, 'from', 'no-reply@example.com'),
            ),
            default => throw new InvalidArgumentException("Unsupported mail transport [{$transport}]."),
        };
    }

    /** @param array<string, mixed> $config */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** @param array<string, mixed> $config */
    private function nullableStringConfig(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $config */
    private function intConfig(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;

        return is_int($value) || is_string($value) || is_float($value) ? (int) $value : $default;
    }
}
