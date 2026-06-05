<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Foundation\Config;
use Fulcrum\GraphQL\Exceptions\ClientException;
use GraphQL\GraphQL;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Visitor;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;

final class QuerySafety
{
    public function __construct(private readonly Config $config) {}

    public function prepare(string $query): QuerySafetyResult
    {
        $document = Parser::parse($query);
        $operationCount = 0;
        $aliasCount = 0;

        Visitor::visit($document, [
            'enter' => static function (mixed $node) use (&$operationCount, &$aliasCount): void {
                if ($node instanceof OperationDefinitionNode) {
                    $operationCount++;
                } elseif ($node instanceof FieldNode && $node->alias !== null) {
                    $aliasCount++;
                }
            },
        ]);

        $this->enforceCount('max_operations', $operationCount, 'OPERATION_LIMIT_EXCEEDED', 'GraphQL operation count');
        $this->enforceCount('max_aliases', $aliasCount, 'ALIAS_LIMIT_EXCEEDED', 'GraphQL alias count');

        $complexity = new QueryComplexity($this->integer('max_complexity', 200));
        $rules = array_values(array_filter(
            GraphQL::getStandardValidationRules(),
            static fn (mixed $rule): bool => !$rule instanceof QueryDepth
                && !$rule instanceof QueryComplexity
                && !$rule instanceof DisableIntrospection,
        ));
        $rules[] = new QueryDepth($this->integer('max_depth', 12));
        $rules[] = $complexity;
        $rules[] = new DisableIntrospection(
            $this->boolean('introspection', true) ? DisableIntrospection::DISABLED : DisableIntrospection::ENABLED,
        );

        return new QuerySafetyResult($document, $rules, $complexity);
    }

    public function maxExecutionMilliseconds(): int
    {
        return $this->integer('max_execution_ms', 0);
    }

    private function enforceCount(string $key, int $actual, string $code, string $label): void
    {
        $maximum = $this->integer($key, 0);

        if ($maximum > 0 && $actual > $maximum) {
            throw new ClientException(
                "{$label} must not exceed {$maximum}; got {$actual}.",
                $code,
                ['maximum' => $maximum, 'actual' => $actual],
            );
        }
    }

    private function integer(string $key, int $default): int
    {
        $value = $this->config->get("graphql.security.{$key}", $default);

        return max(0, is_numeric($value) ? (int) $value : $default);
    }

    private function boolean(string $key, bool $default): bool
    {
        return (bool) $this->config->get("graphql.security.{$key}", $default);
    }
}
