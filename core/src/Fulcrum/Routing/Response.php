<?php

declare(strict_types=1);

namespace Fulcrum\Routing;

/**
 * JSON HTTP response builder.
 *
 * Immutable — each `with*` method returns a new instance.
 */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly mixed $data,
        private readonly int   $statusCode = 200,
        private readonly array $headers    = [],
    ) {}

    // ─── Factories ───────────────────────────────────────────────────────────

    public static function json(mixed $data, int $status = 200): static
    {
        return new static($data, $status, ['Content-Type' => 'application/json']);
    }

    /** @param array<string, mixed> $result  webonyx execution result */
    public static function graphql(array $result, int $status = 200): static
    {
        return static::json($result, $status);
    }

    public static function notFound(): static
    {
        return static::json(['errors' => [['message' => 'Not Found']]], 404);
    }

    public static function methodNotAllowed(): static
    {
        return static::json(['errors' => [['message' => 'Method Not Allowed']]], 405);
    }

    public static function error(string $message, int $status): static
    {
        return static::json(['errors' => [['message' => $message]]], $status);
    }

    public static function noContent(int $status = 204): static
    {
        return new static(null, $status);
    }

    /** @param array<string, string> $headers */
    public static function raw(string $content, int $status = 200, array $headers = []): static
    {
        return new static($content, $status, $headers);
    }

    // ─── Mutation ────────────────────────────────────────────────────────────

    public function withHeader(string $name, string $value): static
    {
        $headers         = $this->headers;
        $headers[$name]  = $value;
        return new static($this->data, $this->statusCode, $headers);
    }

    public function withStatus(int $code): static
    {
        return new static($this->data, $code, $this->headers);
    }

    // ─── Emission ────────────────────────────────────────────────────────────

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->statusCode === 204 || $this->data === null) {
            return;
        }

        echo is_string($this->data)
            ? $this->data
            : json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
