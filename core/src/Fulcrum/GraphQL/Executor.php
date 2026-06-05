<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use GraphQL\GraphQL;
use GraphQL\Error\ClientAware;
use GraphQL\Error\Error;
use GraphQL\Type\Schema;
use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ClientException;
use Psr\Log\LoggerInterface;

/**
 * Executes GraphQL queries against a compiled Schema.
 */
class Executor
{
    public function __construct(
        private readonly Schema $schema,
        private readonly Config $config,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?QuerySafety $querySafety = null,
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
        $startedAt = hrtime(true);

        try {
            $safety = ($this->querySafety ?? new QuerySafety($this->config))->prepare($query);
            $result = GraphQL::executeQuery(
                schema: $this->schema,
                source: $safety->document,
                rootValue: null,
                contextValue: $context,
                variableValues: $variables,
                operationName: $operationName,
                validationRules: $safety->validationRules,
            );

            $debugFlag = $this->config->get('app.debug', false)
                ? \GraphQL\Error\DebugFlag::INCLUDE_DEBUG_MESSAGE | \GraphQL\Error\DebugFlag::INCLUDE_TRACE
                : \GraphQL\Error\DebugFlag::NONE;

            $result->setErrorsHandler(function (array $errors, callable $formatter) use ($context, $operationName): array {
                $formatted = [];

                foreach ($errors as $error) {
                    if ($error instanceof ClientAware && !$error->isClientSafe()) {
                        $this->report($error->getPrevious() ?? $error, $context, $operationName);
                    }

                    $item = $formatter($error);
                    if ($error instanceof Error && $error->getPrevious() === null) {
                        $item['extensions']['code'] ??= $this->validationCode($error->getMessage());
                    }
                    $formatted[] = $item;
                }

                return $formatted;
            });

            $durationMs = $this->durationMilliseconds($startedAt);
            $complexity = $safety->complexityRule->getQueryComplexity();
            $this->reportCompletion($operationName, $context, $durationMs, $complexity, $result->errors !== []);

            $maximumMs = ($this->querySafety ?? new QuerySafety($this->config))->maxExecutionMilliseconds();
            if ($maximumMs > 0 && $durationMs > $maximumMs) {
                return $this->withRequestId([
                    'errors' => [[
                        'message' => "GraphQL execution exceeded {$maximumMs} milliseconds.",
                        'extensions' => [
                            'code' => 'EXECUTION_TIMEOUT',
                            'maximumMs' => $maximumMs,
                            'durationMs' => $durationMs,
                        ],
                    ]],
                ], $context);
            }

            return $this->withRequestId($result->toArray($debugFlag), $context);
        } catch (\Throwable $e) {
            if ($e instanceof ClientException) {
                return $this->withRequestId([
                    'errors' => [$e->toGraphQLError()],
                ], $context);
            }

            if ($e instanceof Error) {
                return $this->withRequestId([
                    'errors' => [[
                        'message' => $e->getMessage(),
                        'extensions' => ['code' => 'GRAPHQL_VALIDATION_FAILED'],
                    ]],
                ], $context);
            }

            $debug = (bool) $this->config->get('app.debug', false);
            $this->report($e, $context, $operationName);

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

            return $this->withRequestId([
                'errors' => [$error],
            ], $context);
        }
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function withRequestId(array $result, ?RequestContext $context): array
    {
        $requestId = $context?->request()->attribute('request_id');

        if (!is_string($requestId) || $requestId === '' || !isset($result['errors']) || !is_array($result['errors'])) {
            return $result;
        }

        foreach ($result['errors'] as $index => $error) {
            if (!is_array($error)) {
                continue;
            }

            $extensions = isset($error['extensions']) && is_array($error['extensions'])
                ? $error['extensions']
                : [];
            $extensions['requestId'] = $requestId;
            $error['extensions'] = $extensions;
            $result['errors'][$index] = $error;
        }

        return $result;
    }

    private function report(\Throwable $exception, ?RequestContext $context, ?string $operationName): void
    {
        $this->logger?->error('GraphQL operation failed.', [
            'exception' => $exception,
            'operation' => $operationName,
            'request_id' => $context?->request()->attribute('request_id'),
        ]);
    }

    private function reportCompletion(
        ?string $operationName,
        ?RequestContext $context,
        float $durationMs,
        int $complexity,
        bool $hasErrors,
    ): void {
        $this->logger?->info('GraphQL operation completed.', [
            'operation' => $operationName,
            'request_id' => $context?->request()->attribute('request_id'),
            'duration_ms' => $durationMs,
            'complexity' => $complexity,
            'has_errors' => $hasErrors,
            'status' => $hasErrors ? 'error' : 'ok',
        ]);
    }

    private function durationMilliseconds(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 3);
    }

    private function validationCode(string $message): string
    {
        return match (true) {
            str_starts_with($message, 'Max query depth') => 'QUERY_DEPTH_EXCEEDED',
            str_starts_with($message, 'Max query complexity') => 'QUERY_COMPLEXITY_EXCEEDED',
            str_starts_with($message, 'GraphQL introspection is not allowed') => 'INTROSPECTION_DISABLED',
            default => 'GRAPHQL_VALIDATION_FAILED',
        };
    }
}
