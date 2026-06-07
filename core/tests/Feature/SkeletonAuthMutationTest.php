<?php

declare(strict_types=1);

use Fulcrum\Auth\TokenManager;
use Fulcrum\Cache\CacheManager;
use Fulcrum\Container\Container;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Routing\Request;
use Fulcrum\Validation\Validator;

require_once __DIR__ . '/../../../skeleton/src/GraphQL/AuthMutation.php';

use App\GraphQL\AuthMutation;

function skeletonAuthMutation(Config $config, TokenManager $tokens): AuthMutation
{
    return new AuthMutation(
        new Validator(),
        $tokens,
        new CacheManager($config),
        $config,
    );
}

function skeletonAuthContext(string $remoteAddress, ?string $forwardedFor = null): RequestContext
{
    $server = ['REMOTE_ADDR' => $remoteAddress];

    if ($forwardedFor !== null) {
        $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
    }

    return new RequestContext(
        new Request('POST', '/graphql', $server, []),
        new Container(),
    );
}

it('uses trusted proxies when throttling login attempts', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array']);
    $config->set('auth.login_rate_limit.max_attempts', 1);
    $config->set('auth.login_rate_limit.decay_seconds', 60);
    $config->set('api.trusted_proxies', ['10.0.0.0/8']);

    $mutation = skeletonAuthMutation($config, $this->createMock(TokenManager::class));
    $args = ['email' => 'ada@example.com', 'password' => 'secret-passphrase'];

    expect(fn () => $mutation->login(null, $args, skeletonAuthContext('10.0.0.5', '198.51.100.10')))
        ->toThrow(ClientException::class, 'Unable to authenticate with the provided credentials.');

    expect(fn () => $mutation->login(null, $args, skeletonAuthContext('10.0.0.5', '198.51.100.11')))
        ->toThrow(ClientException::class, 'Unable to authenticate with the provided credentials.');

    try {
        $mutation->login(null, $args, skeletonAuthContext('10.0.0.5', '198.51.100.10'));
        $this->fail('Expected login throttling to reject the third attempt.');
    } catch (ClientException $exception) {
        expect($exception->getExtensions()['code'])->toBe('RATE_LIMITED')
            ->and($exception->getExtensions()['retryAfter'])->toBe(60);
    }
});

it('ignores forwarded client ip values from untrusted proxies when throttling login attempts', function () {
    $config = new Config(__DIR__ . '/missing');
    $config->set('cache.default', 'array');
    $config->set('cache.stores.array', ['driver' => 'array']);
    $config->set('auth.login_rate_limit.max_attempts', 1);
    $config->set('auth.login_rate_limit.decay_seconds', 60);
    $config->set('api.trusted_proxies', ['192.168.0.0/16']);

    $mutation = skeletonAuthMutation($config, $this->createMock(TokenManager::class));
    $args = ['email' => 'ada@example.com', 'password' => 'secret-passphrase'];

    expect(fn () => $mutation->login(null, $args, skeletonAuthContext('10.0.0.5', '198.51.100.10')))
        ->toThrow(ClientException::class, 'Unable to authenticate with the provided credentials.');

    try {
        $mutation->login(null, $args, skeletonAuthContext('10.0.0.5', '198.51.100.11'));
        $this->fail('Expected login throttling to reuse the remote address for untrusted proxies.');
    } catch (ClientException $exception) {
        expect($exception->getExtensions()['code'])->toBe('RATE_LIMITED')
            ->and($exception->getExtensions()['retryAfter'])->toBe(60);
    }
});
