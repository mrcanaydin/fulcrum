<?php

declare(strict_types=1);

namespace Fulcrum\Container;

use Fulcrum\Container\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Resolves constructor dependencies via PHP Reflection.
 * Handles nested dependency graphs automatically.
 */
final class AutoWirer
{
    public function __construct(private readonly Container $container) {}

    /**
     * Instantiate $class, resolving all constructor dependencies from the container.
     *
     * @param array<string, mixed> $parameters  Explicit overrides keyed by param name
     * @throws ContainerException
     */
    public function make(string $class, array $parameters = []): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(
                "Target [{$class}] is not instantiable. Bind a concrete implementation."
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * @param  ReflectionParameter[] $reflectionParams
     * @param  array<string, mixed>  $provided
     * @return list<mixed>
     */
    private function resolveDependencies(array $reflectionParams, array $provided): array
    {
        $dependencies = [];

        foreach ($reflectionParams as $param) {
            $name = $param->getName();

            // 1. Explicit override wins
            if (array_key_exists($name, $provided)) {
                $dependencies[] = $provided[$name];
                continue;
            }

            $type = $param->getType();

            // 2. Typed, non-builtin → resolve from container
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->container->make($type->getName());
                continue;
            }

            // 3. Has a default value
            if ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            // 4. Nullable with no default → null
            if ($param->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new ContainerException(
                "Unable to resolve primitive parameter \${$name} in [{$param->getDeclaringClass()?->getName()}]. "
                . "Pass it explicitly via make(\$class, ['{$name}' => \$value])."
            );
        }

        return $dependencies;
    }
}
