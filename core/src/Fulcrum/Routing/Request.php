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

    public function query(string $key, mixed $default = null): mixed
    {
        $uri = $this->server('REQUEST_URI', '');
        $query = is_string($uri) ? parse_url($uri, PHP_URL_QUERY) : null;
        $values = [];

        if (is_string($query)) {
            parse_str($query, $values);
        }

        return $values[$key] ?? $default;
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

        if ($this->isTrustedProxy($remoteAddress, $trustedProxies)) {
            $forwardedFor = $this->header('x-forwarded-for');

            if ($forwardedFor !== null) {
                $ips = array_map('trim', explode(',', $forwardedFor));
                foreach ($ips as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
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

    public function explicitLocale(): ?string
    {
        $locale = $this->body['locale'] ?? $this->header('x-locale');

        return is_string($locale) && trim($locale) !== '' ? trim($locale) : null;
    }

    /** @return list<string> */
    public function acceptedLocales(): array
    {
        $header = $this->header('accept-language');

        if ($header === null || trim($header) === '') {
            return [];
        }

        $locales = [];

        foreach (explode(',', $header) as $position => $part) {
            [$locale, $quality] = array_pad(explode(';q=', trim($part), 2), 2, '1');
            $locales[] = ['locale' => trim($locale), 'quality' => (float) $quality, 'position' => $position];
        }

        usort($locales, static fn (array $a, array $b): int => $b['quality'] <=> $a['quality'] ?: $a['position'] <=> $b['position']);

        return array_values(array_filter(array_column($locales, 'locale'), static fn (string $locale): bool => $locale !== '*'));
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self(null, null, $this->server, $this->body, $attributes);
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

    /** @param list<string> $trustedProxies */
    private function isTrustedProxy(string $remoteAddress, array $trustedProxies): bool
    {
        foreach ($trustedProxies as $trustedProxy) {
            if ($trustedProxy === '*') {
                return true;
            }

            if ($trustedProxy === $remoteAddress || $this->ipInCidr($remoteAddress, $trustedProxy)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $prefixLength] = explode('/', $cidr, 2);

        if (
            filter_var($ip, FILTER_VALIDATE_IP) === false
            || filter_var($subnet, FILTER_VALIDATE_IP) === false
            || !ctype_digit($prefixLength)
        ) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $prefix = (int) $prefixLength;
        $maxPrefix = strlen($ipBinary) * 8;

        if ($prefix < 0 || $prefix > $maxPrefix) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0 && substr($ipBinary, 0, $fullBytes) !== substr($subnetBinary, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($ipBinary[$fullBytes]) & $mask) === (ord($subnetBinary[$fullBytes]) & $mask);
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
