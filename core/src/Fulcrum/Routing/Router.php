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
use Fulcrum\Internationalization\LocaleResolver;
use Fulcrum\Internationalization\Translator;
use Fulcrum\GraphQL\Subscriptions\SubscriptionAuthorizer;
use Fulcrum\GraphQL\Subscriptions\SubscriptionBroker;

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

        if ($request->isGet() && $request->path() === '/graphql/stream') {
            return $this->subscriptionStream($request);
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
        $locale = $this->localeResolver()->resolve($request, $user);
        $translator = $this->container->make(Translator::class);
        if ($translator instanceof Translator) {
            $translator->setLocale($locale);
        }
        $context = new RequestContext($request, $this->container, $user, $locale);

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

    private function localeResolver(): LocaleResolver
    {
        $resolver = $this->container->make(LocaleResolver::class);

        if (!$resolver instanceof LocaleResolver) {
            throw new \RuntimeException('Locale resolver is not registered.');
        }

        return $resolver;
    }

    private function subscriptionStream(Request $request): Response
    {
        $authenticator = $this->container->make(TokenAuthenticator::class);
        $resolver = $this->localeResolver();
        $user = $authenticator instanceof TokenAuthenticator ? $authenticator->authenticate($request) : null;
        $context = new RequestContext($request, $this->container, $user, $resolver->resolve($request, $user));
        $topic = $request->query('topic');

        if (!is_string($topic) || $topic === '') {
            return Response::error('Subscription topic is required.', 400);
        }

        $authorizer = $this->container->make(SubscriptionAuthorizer::class);
        $broker = $this->container->make(SubscriptionBroker::class);

        try {
            if (!$authorizer instanceof SubscriptionAuthorizer || !$broker instanceof SubscriptionBroker) {
                throw new \RuntimeException('Subscription services are not registered.');
            }
            $authorizer->authorize($topic, $context);
            $after = $request->header('last-event-id') ?? $request->query('after', '0');
            $events = $broker->events($topic, is_scalar($after) ? (string) $after : '0');
        } catch (ClientException $exception) {
            return Response::json(['errors' => [$exception->toGraphQLError()]], 403);
        }

        $content = "retry: 3000\n\n";
        foreach ($events as $event) {
            $content .= "id: {$event['id']}\n";
            $content .= "event: {$event['topic']}\n";
            $content .= 'data: ' . json_encode($event['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }

        return Response::raw($content, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
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
