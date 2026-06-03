<?php

declare(strict_types=1);

namespace Fulcrum\Routing\Middleware;

use Fulcrum\Cache\CacheManager;
use Fulcrum\Cache\CacheStore;
use Fulcrum\Cache\Stores\ArrayStore;
use Fulcrum\Foundation\Config;
use Fulcrum\Routing\Request;
use Fulcrum\Routing\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    private static ?ArrayStore $fallbackStore = null;

    private readonly CacheStore $cache;

    public function __construct(
        private readonly Config $config,
        ?CacheManager $cache = null,
    ) {
        $this->cache = $cache?->store() ?? self::fallbackStore();
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!(bool) $this->config->get('api.rate_limit.enabled', true)) {
            return $next($request);
        }

        $maxAttempts = $this->intConfig('api.rate_limit.max_attempts', 60);
        $decaySeconds = $this->intConfig('api.rate_limit.decay_seconds', 60);
        $key = $this->key($request);
        $now = time();
        $countKey = "rate_limit:{$key}:count";
        $resetKey = "rate_limit:{$key}:reset";
        $reset = $this->intValue($this->cache->get($resetKey), 0);

        if ($reset <= $now) {
            $reset = $now + $decaySeconds;
            $this->cache->forget($countKey);
            $this->cache->put($resetKey, $reset, $decaySeconds);
        }

        $count = $this->cache->increment($countKey, 1, $decaySeconds);

        if ($count > $maxAttempts) {
            return Response::error('Too many requests.', 429)
                ->withHeader('Retry-After', (string) max(1, $reset - $now));
        }

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $maxAttempts - $count));
    }

    /** @internal test helper */
    public static function clear(): void
    {
        self::fallbackStore()->clear();
    }

    private function key(Request $request): string
    {
        $trustedProxies = $this->stringList($this->config->get('api.trusted_proxies', []));

        return $request->clientIp($trustedProxies) . '|' . $request->path();
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config->get($key, $default);

        return is_int($value) || is_string($value) || is_float($value)
            ? (int) $value
            : $default;
    }

    private function intValue(mixed $value, int $default): int
    {
        return is_int($value) || is_string($value) || is_float($value)
            ? (int) $value
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

    private static function fallbackStore(): ArrayStore
    {
        return self::$fallbackStore ??= new ArrayStore();
    }
}
