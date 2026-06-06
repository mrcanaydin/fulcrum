<?php

declare(strict_types=1);

namespace Fulcrum\Routing\Middleware;

use Fulcrum\Foundation\Config;
use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->enabled()) {
            return $next($request);
        }

        if ($request->isOptions()) {
            if ($this->allowedOrigin($request) === null) {
                return Response::error('CORS origin is not allowed.', 403);
            }

            return $this->withCorsHeaders(Response::noContent(), $request);
        }

        return $this->withCorsHeaders($next($request), $request);
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('api.cors.enabled', true);
    }

    private function withCorsHeaders(Response $response, Request $request): Response
    {
        $allowOrigin = $this->allowedOrigin($request);

        if ($allowOrigin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->stringList($this->config->get('api.cors.allowed_methods', ['POST', 'OPTIONS']))))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->stringList($this->config->get('api.cors.allowed_headers', ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Request-Id']))))
            ->withHeader('Access-Control-Max-Age', $this->stringConfig('api.cors.max_age', '86400'));
    }

    private function allowedOrigin(Request $request): ?string
    {
        $origin = $request->header('origin');
        $allowedOrigins = $this->stringList($this->config->get('api.cors.allowed_origins', ['*']));

        if ($allowedOrigins === []) {
            return null;
        }

        if ($origin === null || trim($origin) === '') {
            return in_array('*', $allowedOrigins, true) ? '*' : null;
        }

        return in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)
            ? $origin
            : null;
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = $this->config->get($key, $default);

        return is_string($value) || is_int($value) || is_float($value) || is_bool($value)
            ? (string) $value
            : $default;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
