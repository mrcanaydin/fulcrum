<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Container\ServiceProvider;
use Fulcrum\Foundation\Config;

/**
 * Registers the GraphQL engine components into the container.
 */
class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AttributeCompiler::class, AttributeCompiler::class);
        $this->container->singleton(SchemaCompiler::class, SchemaCompiler::class);

        $this->container->singleton(Executor::class, function ($app) {
            $config = $app->make(Config::class);
            $attributeCompiler = $app->make(AttributeCompiler::class);
            $schemaCompiler = $app->make(SchemaCompiler::class);

            // Read the list of GraphQL classes to compile from config
            // For example: config/graphql.php -> ['types' => [App\GraphQL\Queries\UserQuery::class, App\GraphQL\Types\UserType::class]]
            $classesToCompile = $config->get('graphql.types', []);

            $typeDefs = $attributeCompiler->compile($classesToCompile);
            $schema   = $schemaCompiler->compile($typeDefs);

            return new Executor($schema, $config);
        });
    }

    public function boot(): void
    {
        // No boot actions required
    }
}
