<?php

declare(strict_types=1);

namespace Fulcrum\Queue;

use Fulcrum\Container\Container;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

class JobRunner
{
    public function __construct(private readonly Container $container) {}

    public function run(Job $job): void
    {
        if (!method_exists($job, 'handle')) {
            throw new RuntimeException(sprintf('Job [%s] must define a handle method.', $job::class));
        }

        $reflection = new ReflectionMethod($job, 'handle');
        $dependencies = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->container->make($type->getName());
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new RuntimeException(sprintf(
                'Unable to resolve job dependency $%s for [%s::handle].',
                $parameter->getName(),
                $job::class,
            ));
        }

        $reflection->invokeArgs($job, $dependencies);
    }
}
