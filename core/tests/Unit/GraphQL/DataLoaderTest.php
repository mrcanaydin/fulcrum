<?php

declare(strict_types=1);

use Fulcrum\Container\Container;
use Fulcrum\GraphQL\RequestContext;
use Fulcrum\Routing\Request;

it('keeps dataloaders scoped to the request context', function () {
    $loads = 0;
    $context = new RequestContext(new Request('POST', '/graphql'), new Container());
    $loader = $context->loaders()->getOrRegister('users.by_id', function (array $keys) use (&$loads): array {
        $loads++;
        $results = [];

        foreach ($keys as $key) {
            if (is_scalar($key)) {
                $results[(string) $key] = ['id' => (string) $key];
            }
        }

        return $results;
    });

    expect($loader->loadMany([1, 2]))->toBe([
        '1' => ['id' => '1'],
        '2' => ['id' => '2'],
    ])
        ->and($loader->load(1))->toBe(['id' => '1'])
        ->and($loads)->toBe(1)
        ->and($context->loaders()->get('users.by_id'))->toBe($loader);
});
