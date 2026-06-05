<?php

declare(strict_types=1);

namespace Fulcrum\Routing;

/**
 * Immutable HTTP request wrapper.
 *
 * Parses the raw PHP super-globals and the JSON body from php://input
 * once on construction; exposes typed accessors for use throughout the
 * framework.
 */
final class Request
{
    /** @var array<string, string> */
    private readonly array $headers;

    /** @var array<string, mixed> */
    private readonly array $body;

    /** @var array<string, mixed> */
    private readonly array $server;

    /** @var array<string, mixed> */
    private readonly array $attributes;

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        ?string $method = null,
        ?string $path = null,
        array $server = [],
        ?array $body = null,
        array $attributes = [],
    )
    {
        $this->server  = $this->buildServer($method, $path, $server);
        $this->headers = $this->parseHeaders();
        $this->body    = $body ?? $this->parseJsonBody();
        $this->attributes = $attributes;
    }

    public static function capture(): static
    {
        return new static();
    }

    // ─── HTTP Basics ─────────────────────────────────────────────────────────

    public function method(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';

        return strtoupper(is_string($method) ? $method : 'GET');
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = is_string($uri) ? parse_url($uri, PHP_URL_PATH) : '/';

        return is_string($path) ? $path : '/';
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isOptions(): bool
    {
        return $this->method() === 'OPTIONS';
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function contentLength(): int
    {
        $length = $this->server('CONTENT_LENGTH', 0);

        return is_numeric($length) ? (int) $length : 0;
    }

    /** @param list<string> $trustedProxies */
    public function clientIp(array $trustedProxies = []): string
    {
        $remoteAddress = $this->server('REMOTE_ADDR', '127.0.0.1');
        $remoteAddress = is_string($remoteAddress) ? $remoteAddress : '127.0.0.1';

        if (in_array($remoteAddress, $trustedProxies, true)) {
            $forwardedFor = $this->header('x-forwarded-for');

            if ($forwardedFor !== null) {
                $ips = array_map('trim', explode(',', $forwardedFor));
                $first = $ips[0] ?? '';

                if ($first !== '') {
                    return $first;
                }
            }
        }

        return $remoteAddress;
    }

    // ─── Headers ─────────────────────────────────────────────────────────────

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');

        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self($this->method(), $this->path(), $this->server, $this->body, $attributes);
    }

    // ─── Body ────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        return $this->body();
    }

    public function graphqlQuery(): string
    {
        $query = $this->body['query'] ?? '';

        return is_string($query) ? $query : '';
    }

    /** @return array<string, mixed> */
    public function graphqlVariables(): array
    {
        $vars = $this->body['variables'] ?? [];
        return is_array($vars) ? $vars : [];
    }

    public function graphqlOperationName(): ?string
    {
        $op = $this->body['operationName'] ?? null;
        return is_string($op) ? $op : null;
    }

    /** @return array<string, mixed> */
    public function graphqlExtensions(): array
    {
        $extensions = $this->body['extensions'] ?? [];

        return is_array($extensions) ? $extensions : [];
    }

    // ─── Parsing ─────────────────────────────────────────────────────────────

    /** @return array<string, string> */
    private function parseHeaders(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name           = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], strict: true)) {
                $name           = strtolower(str_replace('_', '-', $key));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private function buildServer(?string $method, ?string $path, array $server): array
    {
        if ($method === null && $path === null && $server === []) {
            return $this->normaliseServer($_SERVER);
        }

        if ($method !== null) {
            $server['REQUEST_METHOD'] = $method;
        }

        if ($path !== null) {
            $server['REQUEST_URI'] = $path;
        }

        return $server;
    }

    /**
     * @param array<string|int, mixed> $server
     * @return array<string, mixed>
     */
    private function normaliseServer(array $server): array
    {
        $normalised = [];

        foreach ($server as $key => $value) {
            if (is_string($key)) {
                $normalised[$key] = $value;
            }
        }

        return $normalised;
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw     = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
