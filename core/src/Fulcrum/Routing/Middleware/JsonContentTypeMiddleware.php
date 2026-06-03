<?php

declare(strict_types=1);

namespace Fulcrum\Routing\Middleware;

use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

class JsonContentTypeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->isPost() || $request->path() !== '/graphql') {
            return $next($request);
        }

        $contentType = $request->header('content-type') ?? '';

        if (!str_contains(strtolower($contentType), 'application/json')) {
            return Response::error('Content-Type must be application/json.', 415);
        }

        return $next($request);
    }
}
