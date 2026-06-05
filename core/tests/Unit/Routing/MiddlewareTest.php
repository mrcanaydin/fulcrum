<?php

declare(strict_types=1);

use Fulcrum\Foundation\Config;
use Fulcrum\Cache\CacheManager;
use Fulcrum\Container\Container;
use Fulcrum\Routing\Middleware\BodySizeLimitMiddleware;
use Fulcrum\Routing\Middleware\CorsMiddleware;
use Fulcrum\Routing\Middleware\JsonContentTypeMiddleware;
use Fulcrum\Routing\Middleware\MiddlewarePipeline;
use Fulcrum\Routing\Middleware\RateLimitMiddleware;
use Fulcrum\Routing\Middleware\RequestIdMiddleware;
use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;
use Fulcrum\Routing\Router;
use Fulcrum\Database\DatabaseManager;
use Fulcrum\Queue\QueueManager;
use Fulcrum\Storage\StorageManager;
use Fulcrum\Observability\HealthChecker;

function middlewareConfig(): Config
{
    return new Config(__DIR__ . '/missing');
}

it('adds cors headers and handles preflight requests', function () {
    $config = middlewareConfig();
    $middleware = new CorsMiddleware($config);
    $request = new Request('OPTIONS', '/graphql', ['HTTP_ORIGIN' => 'https://app.example'], []);

    $response = $middleware->handle($request, fn () => Response::json(['ok' => true]));

    expect($response->getStatusCode())->toBe(204)
        ->and($response->getHeaders()['Access-Control-Allow-Origin'])->toBe('https://app.example');
});

it('rejects oversized request bodies', function () {
    $config = middlewareConfig();
    $config->set('api.max_body_bytes', 10);

    $response = (new BodySizeLimitMiddleware($config))->handle(
        new Request('POST', '/graphql', ['CONTENT_LENGTH' => '11'], []),
        fn () => Response::json(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(413);
});

it('requires json content type for graphql posts', function () {
    $response = (new JsonContentTypeMiddleware())->handle(
        new Request('POST', '/graphql', ['CONTENT_TYPE' => 'text/plain'], []),
        fn () => Response::json(['ok' => true])
    );

    expect($response->getStatusCode())->toBe(415);
});

it('adds request ids to responses', function () {
    $requestId = null;
    $response = (new RequestIdMiddleware())->handle(
        new Request('POST', '/graphql', ['HTTP_X_REQUEST_ID' => 'req-123'], []),
        function (Request $request) use (&$requestId): Response {
            $requestId = $request->attribute('request_id');

            return Response::json(['ok' => true]);
        }
    );

    expect($response->getHeaders()['X-Request-Id'])->toBe('req-123')
        ->and($requestId)->toBe('req-123');
});

it('rate limits by client ip and path', function () {
    RateLimitMiddleware::clear();

    $config = middlewareConfig();
    $config->set('api.rate_limit.max_attempts', 1);
    $config->set('api.rate_limit.decay_seconds', 60);
    $config->set('cache.default', 'array');

    $middleware = new RateLimitMiddleware($config, new CacheManager($config));
    $request = new Request('POST', '/graphql', ['REMOTE_ADDR' => '10.0.0.5'], []);

    $first = $middleware->handle($request, fn () => Response::json(['ok' => true]));
    $second = $middleware->handle($request, fn () => Response::json(['ok' => true]));

    expect($first->getStatusCode())->toBe(200)
        ->and($second->getStatusCode())->toBe(429)
        ->and($second->getHeaders())->toHaveKey('Retry-After');
});

it('uses forwarded ip only for trusted proxies', function () {
    $request = new Request('POST', '/graphql', [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.7, 10.0.0.1',
    ], []);

    expect($request->clientIp(['10.0.0.1']))->toBe('203.0.113.7')
        ->and($request->clientIp())->toBe('10.0.0.1');
});

it('runs middleware in order around the handler', function () {
    $pipeline = new MiddlewarePipeline([
        new RequestIdMiddleware(),
    ]);

    $response = $pipeline->handle(
        new Request('POST', '/graphql', ['HTTP_X_REQUEST_ID' => 'req-456'], []),
        fn () => Response::json(['ok' => true])
    );

    expect($response->getHeaders()['X-Request-Id'])->toBe('req-456');
});

it('serves json metadata and real liveness and readiness endpoints', function () {
    $container = new Container();
    $config = middlewareConfig();
    $storageRoot = sys_get_temp_dir() . '/fulcrum-health-' . bin2hex(random_bytes(6));
    mkdir($storageRoot, 0777, true);
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:']);
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array']);
    $config->set('queue.default', 'sync');
    $config->set('queue.connections.sync', ['driver' => 'sync']);
    $config->set('storage.default', 'local');
    $config->set('storage.disks.local', ['driver' => 'local', 'root' => $storageRoot]);
    $container->instance(Config::class, $config);
    $container->instance(HealthChecker::class, new HealthChecker(
        $config,
        new DatabaseManager($config),
        new CacheManager($config),
        new QueueManager($config, new DatabaseManager($config)),
        new StorageManager($config),
    ));

    $router = new Router($container);
    $root = $router->handle(new Request('GET', '/', [], []));
    $live = $router->handle(new Request('GET', '/health/live', [], []));
    $ready = $router->handle(new Request('GET', '/health/ready', [], []));
    $health = $router->handle(new Request('GET', '/health', [], []));

    expect($root->getStatusCode())->toBe(200)
        ->and($root->getData()['mode'])->toBe('headless')
        ->and($live->getStatusCode())->toBe(200)
        ->and($live->getData())->toBe(['status' => 'ok'])
        ->and($ready->getStatusCode())->toBe(200)
        ->and($ready->getData()['checks'])->toHaveKeys(['database', 'cache', 'queue', 'storage'])
        ->and($health->getStatusCode())->toBe(200)
        ->and($health->getData()['status'])->toBe('ok');

    rmdir($storageRoot . '/.fulcrum-health');
    rmdir($storageRoot);
});

it('returns service unavailable when a readiness dependency fails', function () {
    $container = new Container();
    $config = middlewareConfig();
    $config->set('database.default', 'sqlite');
    $config->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => '/missing/fulcrum/health.sqlite',
    ]);
    $config->set('health.checks.cache', false);
    $config->set('health.checks.queue', false);
    $config->set('health.checks.storage', false);
    $config->set('cache.default', 'unsupported');
    $container->instance(Config::class, $config);

    $router = new Router($container);
    $live = $router->handle(new Request('GET', '/health/live', [], []));
    $response = $router->handle(new Request('GET', '/health/ready', [], []));

    expect($live->getStatusCode())->toBe(200)
        ->and($response->getStatusCode())->toBe(503)
        ->and($response->getData()['status'])->toBe('unhealthy')
        ->and($response->getData()['checks']['database']['status'])->toBe('failed');
});

it('keeps graphql execution post-only', function () {
    $container = new Container();
    $config = middlewareConfig();
    $config->set('cache.default', 'array');
    $container->instance(Config::class, $config);

    $response = (new Router($container))->handle(new Request('GET', '/graphql', [], []));

    expect($response->getStatusCode())->toBe(405)
        ->and($response->getHeaders()['Allow'])->toBe('POST, OPTIONS');
});
