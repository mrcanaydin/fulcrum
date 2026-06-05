<?php

declare(strict_types=1);

namespace Fulcrum\Routing;

use Fulcrum\Container\Container;
use Fulcrum\Auth\TokenAuthenticator;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Executor;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\GraphQL\PersistedQueryManager;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\Routing\Middleware\BodySizeLimitMiddleware;
use Fulcrum\Routing\Middleware\CorsMiddleware;
use Fulcrum\Routing\Middleware\JsonContentTypeMiddleware;
use Fulcrum\Routing\Middleware\MiddlewareInterface;
use Fulcrum\Routing\Middleware\MiddlewarePipeline;
use Fulcrum\Routing\Middleware\RateLimitMiddleware;
use Fulcrum\Routing\Middleware\RequestIdMiddleware;
use Fulcrum\Observability\HealthChecker;

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
        if ($request->isGet() && in_array($request->path(), ['/health', '/health/live', '/health/ready', '/ready'], true)) {
            return $this->route($request);
        }

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
                    'liveness' => '/health/live',
                    'readiness' => '/health/ready',
                ],
            ]);
        }

        if ($request->isGet() && $request->path() === '/health/live') {
            return Response::json(['status' => 'ok']);
        }

        if ($request->isGet() && in_array($request->path(), ['/health', '/health/ready', '/ready'], true)) {
            $checker = $this->container->make(HealthChecker::class);

            if (!$checker instanceof HealthChecker) {
                throw new \RuntimeException('Health checker is not registered.');
            }

            $result = $checker->readiness();

            return Response::json($result->toArray(), $result->healthy ? 200 : 503);
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

        try {
            $query = $this->persistedQueries()->resolve($request->graphqlQuery(), $request->graphqlExtensions());
        } catch (ClientException $exception) {
            return Response::graphql(['errors' => [$exception->toGraphQLError()]]);
        }

        $result = $executor->execute(
            $query,
            $variables === [] ? null : $variables,
            $request->graphqlOperationName(),
            $context
        );

        return Response::graphql($result);
    }

    private function persistedQueries(): PersistedQueryManager
    {
        $manager = $this->container->make(PersistedQueryManager::class);

        if (!$manager instanceof PersistedQueryManager) {
            throw new \RuntimeException('Persisted query manager is not registered.');
        }

        return $manager;
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
