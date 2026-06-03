<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use Fulcrum\Foundation\Config;
use Fulcrum\Validation\ValidationException;

/**
 * Executes GraphQL queries against a compiled Schema.
 */
class Executor
{
    public function __construct(
        private readonly Schema $schema,
        private readonly Config $config,
    ) {}

    /**
     * @param string $query
     * @param array<string, mixed>|null $variables
     * @param string|null $operationName
     * @param RequestContext|null $context
     * @return array<string, mixed>
     */
    public function execute(
        string $query,
        ?array $variables = null,
        ?string $operationName = null,
        ?RequestContext $context = null
    ): array {
        try {
            $result = GraphQL::executeQuery(
                schema: $this->schema,
                source: $query,
                rootValue: null,
                contextValue: $context,
                variableValues: $variables,
                operationName: $operationName
            );

            $debugFlag = $this->config->get('app.debug', false)
                ? \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE | \GraphQL\Error\DebugFlag::INCLUDE_TRACE
                : \GraphQL\Error\DebugFlag::NONE;
            
            return $result->toArray($debugFlag);
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) {
                return [
                    'errors' => [$e->toGraphQLError()],
                ];
            }

            $debug = (bool) $this->config->get('app.debug', false);

            $error = $debug
                ? [
                    'message' => $e->getMessage(),
                    'extensions' => [
                        'category' => 'internal',
                        'class' => $e::class,
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ]
                : ['message' => 'Internal server error.'];

            if ($debug) {
                $error['extensions']['trace'] = explode("\n", $e->getTraceAsString());
            }

            return [
                'errors' => [$error],
            ];
        }
    }
}
