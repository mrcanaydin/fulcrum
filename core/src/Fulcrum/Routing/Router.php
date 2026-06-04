<?php

declare(strict_types=1);

namespace Fulcrum\Routing;

use Fulcrum\Container\Container;
use Fulcrum\Auth\TokenAuthenticator;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Executor;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Routing\Middleware\BodySizeLimitMiddleware;
use Fulcrum\Routing\Middleware\CorsMiddleware;
use Fulcrum\Routing\Middleware\JsonContentTypeMiddleware;
use Fulcrum\Routing\Middleware\MiddlewareInterface;
use Fulcrum\Routing\Middleware\MiddlewarePipeline;
use Fulcrum\Routing\Middleware\RateLimitMiddleware;
use Fulcrum\Routing\Middleware\RequestIdMiddleware;

/**
 * Minimalist router.
 *
 * Fulcrum exposes POST /graphql plus JSON metadata and health endpoints
 * for API previews and infrastructure probes.
 *
 * Cross-cutting API concerns are handled by the configured middleware pipeline.
 */
class Router
{
    public function __construct(private readonly Container $container) {}

    public function dispatch(Request $request): void
    {
        $this->handle($request)->send();
    }

    public function handle(Request $request): Response
    {
        return $this->pipeline()->handle($request, fn (Request $request): Response => $this->route($request));
    }

    private function route(Request $request): Response
    {
        if ($request->isGet() && $request->path() === '/') {
            return Response::json([
                'name' => 'Fulcrum',
                'mode' => 'headless',
                'status' => 'ok',
                'endpoints' => [
                    'graphql' => '/graphql',
                    'health' => '/health',
                ],
            ]);
        }

        if ($request->isGet() && $request->path() === '/health') {
            return Response::json(['status' => 'ok']);
        }

        if ($request->path() !== '/graphql') {
            return Response::notFound();
        }

        if (!$request->isPost()) {
            return Response::methodNotAllowed()
                ->withHeader('Allow', 'POST, OPTIONS');
        }

        $executor = $this->container->make(Executor::class);

        if (!$executor instanceof Executor) {
            throw new \RuntimeException('GraphQL executor is not registered.');
        }

        // Authenticate the request
        $authenticator = $this->container->make(TokenAuthenticator::class);

        if (!$authenticator instanceof TokenAuthenticator) {
            throw new \RuntimeException('Token authenticator is not registered.');
        }

        $user = $authenticator->authenticate($request);

        $context = new RequestContext($request, $this->container, $user);

        $variables = $request->graphqlVariables();

        $result = $executor->execute(
            $request->graphqlQuery(),
            $variables === [] ? null : $variables,
            $request->graphqlOperationName(),
            $context
        );

        return Response::graphql($result);
    }

    private function pipeline(): MiddlewarePipeline
    {
        $config = $this->container->make(Config::class);
        $classes = $config instanceof Config
            ? $config->get('api.middleware', $this->defaultMiddleware())
            : $this->defaultMiddleware();

        if (!is_array($classes)) {
            $classes = $this->defaultMiddleware();
        }

        $middleware = [];

        foreach ($classes as $class) {
            if (!is_string($class)) {
                continue;
            }

            $instance = $this->container->make($class);

            if ($instance instanceof MiddlewareInterface) {
                $middleware[] = $instance;
            }
        }

        return new MiddlewarePipeline($middleware);
    }

    /** @return list<class-string<MiddlewareInterface>> */
    private function defaultMiddleware(): array
    {
        return [
            CorsMiddleware::class,
            RequestIdMiddleware::class,
            BodySizeLimitMiddleware::class,
            RateLimitMiddleware::class,
            JsonContentTypeMiddleware::class,
        ];
    }
}
