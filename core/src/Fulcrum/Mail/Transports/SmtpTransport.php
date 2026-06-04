<?php

declare(strict_types=1);

namespace Fulcrum\Mail\Transports;

use Fulcrum\Mail\MailTransport;
use Fulcrum\Mail\Message;
use RuntimeException;

class SmtpTransport implements MailTransport
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly string $encryption = 'tls',
        private readonly string $defaultFrom = 'no-reply@example.com',
    ) {}

    public function send(Message $message): void
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $errno = 0;
        $error = '';
        $socket = stream_socket_client($remote, $errno, $error, 30);

        if (!is_resource($socket)) {
            throw new RuntimeException(
                "Could not connect to SMTP server: {$error}",
                is_int($errno) ? $errno : 0,
            );
        }

        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO localhost', [250]);

        if ($this->encryption === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);
            if (stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new RuntimeException('Could not enable TLS for SMTP connection.');
            }

            $this->command($socket, 'EHLO localhost', [250]);
        }

        if ($this->username !== null && $this->password !== null) {
            $this->command($socket, 'AUTH LOGIN', [334]);
            $this->command($socket, base64_encode($this->username), [334]);
            $this->command($socket, base64_encode($this->password), [235]);
        }

        $from = $this->address($message->from ?: $this->defaultFrom);
        $to = $this->address($message->to);
        $this->command($socket, "MAIL FROM:<{$from}>", [250]);
        $this->command($socket, "RCPT TO:<{$to}>", [250, 251]);
        $this->command($socket, 'DATA', [354]);
        fwrite($socket, $this->payload($message, $from) . "\r\n.\r\n");
        $this->expect($socket, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    /**
     * @param resource $socket
     * @param list<int> $expected
     */
    private function command($socket, string $command, array $expected): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $expected);
    }

    /**
     * @param resource $socket
     * @param list<int> $expected
     */
    private function expect($socket, array $expected): void
    {
        $line = fgets($socket);

        if (!is_string($line)) {
            throw new RuntimeException('SMTP server closed the connection.');
        }

        $code = (int) substr($line, 0, 3);

        while (isset($line[3]) && $line[3] === '-') {
            $line = fgets($socket);

            if (!is_string($line)) {
                break;
            }
        }

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException("Unexpected SMTP response [{$code}].");
        }
    }

    private function payload(Message $message, string $from): string
    {
        $headers = array_merge([
            'From' => $from,
            'To' => $this->header($message->to),
            'Subject' => $message->subject,
            'MIME-Version' => '1.0',
            'Content-Type' => $message->html !== null ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8',
        ], $message->headers());

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $this->header($name) . ': ' . $this->header($value);
        }

        $lines[] = '';
        $lines[] = $message->html ?? $message->text;

        return implode("\r\n", $lines);
    }

    private function address(string $value): string
    {
        $address = trim(str_replace(["\r", "\n"], '', $value));

        if ($address === '') {
            throw new RuntimeException('SMTP message requires a non-empty email address.');
        }

        return $address;
    }

    private function header(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }
}
