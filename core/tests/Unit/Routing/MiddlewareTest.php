<?php

declare(strict_types=1);

use Fulcrum\Foundation\Config;
use Fulcrum\Cache\CacheManager;
use Fulcrum\Routing\Middleware\BodySizeLimitMiddleware;
use Fulcrum\Routing\Middleware\CorsMiddleware;
use Fulcrum\Routing\Middleware\JsonContentTypeMiddleware;
use Fulcrum\Routing\Middleware\MiddlewarePipeline;
use Fulcrum\Routing\Middleware\RateLimitMiddleware;
use Fulcrum\Routing\Middleware\RequestIdMiddleware;
use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

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
    $response = (new RequestIdMiddleware())->handle(
        new Request('POST', '/graphql', ['HTTP_X_REQUEST_ID' => 'req-123'], []),
        fn () => Response::json(['ok' => true])
    );

    expect($response->getHeaders()['X-Request-Id'])->toBe('req-123');
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
