<?php

declare(strict_types=1);

namespace Fulcrum\Routing\Middleware;

use Fulcrum\Foundation\Config;
use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

class BodySizeLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, callable $next): Response
    {
        $maxBytes = $this->intConfig('api.max_body_bytes', 1048576);

        if ($maxBytes > 0 && $request->contentLength() > $maxBytes) {
            return Response::error('Request body is too large.', 413);
        }

        return $next($request);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config->get($key, $default);

        return is_int($value) || is_string($value) || is_float($value)
            ? (int) $value
            : $default;
    }
}
