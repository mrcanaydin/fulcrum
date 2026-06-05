<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Container\ServiceProvider;
use Fulcrum\Container\Contracts\ContainerInterface;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Scalars\BuiltInScalars;
use Fulcrum\GraphQL\Pagination\PageInfoType;
use GraphQL\Type\Definition\ScalarType;
use Psr\Log\LoggerInterface;
use Fulcrum\Cache\CacheManager;
use GraphQL\Utils\SchemaPrinter;

/**
 * Registers the GraphQL engine components into the container.
 */
class GraphQLServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(AttributeCompiler::class, AttributeCompiler::class);
        $this->container->singleton(ResolverMetrics::class, function ($app): ResolverMetrics {
            $config = $app->make(Config::class);
            $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;

            return new ResolverMetrics(
                $config,
                $logger instanceof LoggerInterface ? $logger : null,
            );
        });
        $this->container->singleton(SchemaCompiler::class, function ($app): SchemaCompiler {
            return new SchemaCompiler(
                $app,
                $app->make(ResolverMetrics::class),
            );
        });
        $this->container->singleton(QuerySafety::class, QuerySafety::class);
        $this->container->singleton(MutationTransaction::class, MutationTransaction::class);
        $this->container->singleton(PersistedQueryManager::class, PersistedQueryManager::class);
        $this->container->singleton(SchemaRegistry::class, SchemaRegistry::class);

        $this->container->singleton(Executor::class, function ($app) {
            $config = $app->make(Config::class);
            $attributeCompiler = $app->make(AttributeCompiler::class);
            $schemaCompiler = $app->make(SchemaCompiler::class);
            $querySafety = $app->make(QuerySafety::class);

            // Read the list of GraphQL classes to compile from config
            // For example: config/graphql.php -> ['types' => [App\GraphQL\Queries\UserQuery::class, App\GraphQL\Types\UserType::class]]
            $classesToCompile = $config->get('graphql.types', []);
            if (is_array($classesToCompile) && !in_array(PageInfoType::class, $classesToCompile, true)) {
                $classesToCompile[] = PageInfoType::class;
            }

            $typeDefs = $attributeCompiler->compile($classesToCompile);
            $schema   = $schemaCompiler->compile($typeDefs, $this->scalarTypes($app, $config));
            $logger = $app->bound(LoggerInterface::class)
                ? $app->make(LoggerInterface::class)
                : null;

            $executor = new Executor(
                $schema,
                $config,
                $logger instanceof LoggerInterface ? $logger : null,
                $querySafety instanceof QuerySafety ? $querySafety : null,
            );

            $this->cacheSchemaSnapshot($app, $config, $schema);

            return $executor;
        });
    }

    public function boot(): void
    {
        // No boot actions required
    }

    /** @return array<string, ScalarType> */
    private function scalarTypes(ContainerInterface $app, Config $config): array
    {
        $scalars = BuiltInScalars::all();
        $configured = $config->get('graphql.scalars', []);

        if (!is_array($configured)) {
            return $scalars;
        }

        foreach ($configured as $name => $scalar) {
            if (is_string($scalar) && class_exists($scalar)) {
                $scalar = $app->make($scalar);
            }

            if ($scalar instanceof ScalarType) {
                $scalars[is_string($name) ? $name : $scalar->name] = $scalar;
            }
        }

        return $scalars;
    }

    private function cacheSchemaSnapshot(ContainerInterface $app, Config $config, \GraphQL\Type\Schema $schema): void
    {
        if (!(bool) $config->get('graphql.schema_cache.enabled', true) || !$app->bound(CacheManager::class)) {
            return;
        }

        $cache = $app->make(CacheManager::class);
        if (!$cache instanceof CacheManager) {
            return;
        }

        $sdl = SchemaPrinter::doPrint($schema);
        $key = $config->get('graphql.schema_cache.key', 'graphql:schema:snapshot');
        $ttl = $config->get('graphql.schema_cache.ttl', 86400);
        $cache->store()->put(
            is_string($key) && $key !== '' ? $key : 'graphql:schema:snapshot',
            ['hash' => hash('sha256', $sdl), 'sdl' => $sdl],
            is_numeric($ttl) ? max(0, (int) $ttl) : 86400,
        );
    }
}
