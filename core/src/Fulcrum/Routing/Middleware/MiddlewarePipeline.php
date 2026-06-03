<?php

declare(strict_types=1);

namespace Fulcrum\Routing\Middleware;

use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

class MiddlewarePipeline
{
    /** @param list<MiddlewareInterface> $middleware */
    public function __construct(private readonly array $middleware = []) {}

    /** @param callable(Request): Response $handler */
    public function handle(Request $request, callable $handler): Response
    {
        $next = array_reduce(
            array_reverse($this->middleware),
            fn (callable $next, MiddlewareInterface $middleware): callable =>
                fn (Request $request): Response => $middleware->handle($request, $next),
            $handler
        );

        return $next($request);
    }
}
