<?php

declare(strict_types=1);

namespace Fulcrum\Routing\Middleware;

use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $requestId = $request->header('x-request-id') ?: bin2hex(random_bytes(16));

        return $next($request)->withHeader('X-Request-Id', $requestId);
    }
}
