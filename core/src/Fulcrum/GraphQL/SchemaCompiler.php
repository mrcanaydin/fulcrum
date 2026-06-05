<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use Fulcrum\Container\Contracts\ContainerInterface;
use Fulcrum\GraphQL\Exceptions\ForbiddenException;
use Fulcrum\GraphQL\Exceptions\UnauthenticatedException;
use Fulcrum\GraphQL\Exceptions\IdempotencyException;

/**
 * Converts an array of TypeDef IR objects into a webonyx GraphQL Schema.
 */
class SchemaCompiler
{
    /** @var array<string, Type> */
    private array $typeCache = [];

    /** @var array<string, TypeDef> */
    private array $namedDefs = [];

    /** @var array<string, ScalarType> */
    private array $scalarTypes = [];

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?ResolverMetrics $resolverMetrics = null,
    ) {}

    /**
     * @param array<int, TypeDef> $typeDefs
     * @param array<string, ScalarType> $scalarTypes
     */
    public function compile(array $typeDefs, array $scalarTypes = []): Schema
    {
        $queryFields    = [];
        $mutationFields = [];
        $subscriptionFields = [];
        $this->typeCache = [];
        $this->namedDefs = [];
        $this->scalarTypes = $scalarTypes;

        // 1. First pass: Register named definitions
        foreach ($typeDefs as $def) {
            if (in_array($def->kind, [TypeDef::KIND_OBJECT, TypeDef::KIND_INPUT, TypeDef::KIND_ENUM], true)) {
                $this->namedDefs[$def->name] = $def;
            }
        }

        // 2. Second pass: Collect Query/Mutation fields (which may depend on registered objects)
        foreach ($typeDefs as $def) {
            if ($def->kind === TypeDef::KIND_QUERY) {
                $queryFields = array_merge($queryFields, $this->compileFields($def->fields, $def->className));
            } elseif ($def->kind === TypeDef::KIND_MUTATION) {
                $mutationFields = array_merge($mutationFields, $this->compileFields($def->fields, $def->className));
            } elseif ($def->kind === TypeDef::KIND_SUBSCRIPTION) {
                $subscriptionFields = array_merge($subscriptionFields, $this->compileFields($def->fields, $def->className));
            }
        }

        $subscriptionType = null;
        if (!empty($subscriptionFields)) {
            $subscriptionType = new ObjectType([
                'name' => 'Subscription',
                'fields' => $subscriptionFields,
            ]);
            $this->typeCache['Subscription'] = $subscriptionType;
        }

        // 2. Build root Query type
        $queryType = null;
        if (!empty($queryFields)) {
            $queryType = new ObjectType([
                'name'   => 'Query',
                'fields' => $queryFields,
            ]);
            $this->typeCache['Query'] = $queryType;
        }

        // 3. Build root Mutation type
        $mutationType = null;
        if (!empty($mutationFields)) {
            $mutationType = new ObjectType([
                'name'   => 'Mutation',
                'fields' => $mutationFields,
            ]);
            $this->typeCache['Mutation'] = $mutationType;
        }

        // 4. Return the Schema
        return new Schema([
            'query'    => $queryType,
            'mutation' => $mutationType,
            'subscription' => $subscriptionType,
            'typeLoader' => fn(string $name) => $this->loadType($name),
        ]);
    }

    /**
     * @param array<int, FieldDef> $fields
     * @param string $className The class containing the resolver methods
     * @return array<string, array<string, mixed>>
     */
    private function compileFields(array $fields, string $className): array
    {
        $compiled = [];

        foreach ($fields as $field) {
            $compiledArgs = [];
            foreach ($field->args as $arg) {
                $compiledArgs[$arg->name] = [
                    'type'        => $this->parseTypeString($arg->type),
                    'description' => $arg->description,
                ];
                if ($arg->hasDefault) {
                    $compiledArgs[$arg->name]['defaultValue'] = $arg->defaultValue;
                }
            }

            $compiled[$field->name] = [
                'type'        => $this->parseTypeString($field->type),
                'description' => $field->description,
                'deprecationReason' => $field->deprecationReason,
                'args'        => $compiledArgs,
                'resolve'     => $this->createResolver(
                    $className,
                    $field->methodName,
                    $field->authenticated,
                    $field->requiredAbilities,
                    $field->transactional,
                    $field->idempotent,
                ),
            ];
        }

        return $compiled;
    }

    private function createResolver(
        string $className,
        string $methodName,
        bool $authenticated,
        array $requiredAbilities = [],
        bool $transactional = false,
        bool $idempotent = false,
    ): callable
    {
        $resolver = function ($root, array $args, RequestContext $context) use (
            $className,
            $methodName,
            $authenticated,
            $requiredAbilities,
            $transactional,
            $idempotent,
        ) {
            if (($authenticated || !empty($requiredAbilities)) && !$context->isAuth()) {
                throw new UnauthenticatedException();
            }

            if (!empty($requiredAbilities)) {
                $user = $context->user();
                $tokenAbilities = $user['_token']['abilities'] ?? [];
                
                if (!in_array('*', $tokenAbilities)) {
                    foreach ($requiredAbilities as $ability) {
                        if (!in_array($ability, $tokenAbilities)) {
                            throw new ForbiddenException("Missing required ability: {$ability}");
                        }
                    }
                }
            }

            // Simple property access on root object if it's an array or object and method isn't a resolver on a specific class
            // Actually, if it's an ObjectType field, we might just want to resolve properties.
            // But here $className is the class where the attribute was defined.
            // If the root object IS an instance of $className, we can call it directly.
            // If the field is on Query/Mutation, we resolve $className via container.

            $invoke = function () use ($root, $args, $context, $className, $methodName): mixed {
                if ($root instanceof $className && method_exists($root, $methodName)) {
                    return $root->{$methodName}($root, $args, $context);
                }
            
                // Try property access if no method
                if ($root instanceof $className && property_exists($root, $methodName)) {
                    return $root->{$methodName};
                }
            
                // Array/Object property fallback for basic ObjectTypes
                if (is_array($root) && array_key_exists($methodName, $root)) {
                    return $root[$methodName];
                }
                if (is_object($root) && property_exists($root, $methodName)) {
                    return $root->{$methodName};
                }

                // Resolve controller/resolver from DI container (typical for Query/Mutation)
                $resolverInstance = $this->container->get($className);
                return $resolverInstance->{$methodName}($root, $args, $context);
            };

            if (!$transactional) {
                return $invoke();
            }

            $transactions = $this->container->get(MutationTransaction::class);
            if (!$transactions instanceof MutationTransaction) {
                throw new \RuntimeException('Mutation transaction service is not registered.');
            }

            if (!$idempotent) {
                return $transactions->run($invoke);
            }

            $key = $context->request()->header('idempotency-key');
            if ($key === null) {
                throw new IdempotencyException(
                    'This mutation requires an Idempotency-Key header.',
                    'IDEMPOTENCY_KEY_REQUIRED',
                );
            }

            $actor = $context->user();
            $actorId = is_array($actor) && isset($actor['id']) && is_scalar($actor['id'])
                ? (string) $actor['id']
                : 'anonymous';
            $scope = hash('sha256', $actorId . ':' . $className . ':' . $methodName);
            $fingerprint = hash('sha256', serialize($args));

            return $transactions->idempotent($scope, $key, $fingerprint, $invoke);
        };

        return function ($root, array $args, RequestContext $context) use (
            $resolver,
            $className,
            $methodName,
        ): mixed {
            if ($this->resolverMetrics === null) {
                return $resolver($root, $args, $context);
            }

            return $this->resolverMetrics->measure(
                $className,
                $methodName,
                $context,
                fn (): mixed => $resolver($root, $args, $context),
            );
        };
    }

    private function loadType(string $name): ?Type
    {
        if (isset($this->typeCache[$name])) {
            return $this->typeCache[$name];
        }

        if (isset($this->scalarTypes[$name])) {
            return $this->typeCache[$name] = $this->scalarTypes[$name];
        }

        if (isset($this->namedDefs[$name])) {
            $def = $this->namedDefs[$name];

            $this->typeCache[$name] = match ($def->kind) {
                TypeDef::KIND_OBJECT => new ObjectType([
                    'name' => $def->name,
                    'description' => $def->description,
                    'fields' => fn() => $this->compileFields($def->fields, $def->className),
                ]),
                TypeDef::KIND_INPUT => new InputObjectType([
                    'name' => $def->name,
                    'description' => $def->description,
                    'fields' => fn() => $this->compileInputFields($def->inputFields),
                ]),
                TypeDef::KIND_ENUM => new PhpEnumType($def->className, $def->name, $def->description),
                default => throw new \Exception("Unknown GraphQL type kind: {$def->kind}"),
            };

            return $this->typeCache[$name];
        }

        return null;
    }

    /**
     * Parses a string like "String!", "[User]", "[ID!]!" into webonyx Type instances.
     */
    private function parseTypeString(string $typeStr): Type
    {
        $typeStr = trim($typeStr);
        $isNonNull = false;

        if (str_ends_with($typeStr, '!')) {
            $isNonNull = true;
            $typeStr = substr($typeStr, 0, -1);
        }

        $isList = false;
        if (str_starts_with($typeStr, '[') && str_ends_with($typeStr, ']')) {
            $isList = true;
            $typeStr = substr($typeStr, 1, -1);
        }

        $typeStr = trim($typeStr);
        $innerIsNonNull = false;
        
        if (str_ends_with($typeStr, '!')) {
            $innerIsNonNull = true;
            $typeStr = substr($typeStr, 0, -1);
        }

        $baseType = match ($typeStr) {
            'String'  => Type::string(),
            'Int'     => Type::int(),
            'Float'   => Type::float(),
            'Boolean' => Type::boolean(),
            'ID'      => Type::id(),
            default   => $this->loadType($typeStr), // Lazy load custom object types
        };

        if ($baseType === null) {
            throw new \RuntimeException("Unknown GraphQL type: {$typeStr}");
        }

        if ($innerIsNonNull) {
            $baseType = Type::nonNull($baseType);
        }

        if ($isList) {
            $baseType = Type::listOf($baseType);
        }

        if ($isNonNull) {
            $baseType = Type::nonNull($baseType);
        }

        return $baseType;
    }

    /**
     * @param array<int, InputFieldDef> $fields
     * @return array<string, array<string, mixed>>
     */
    private function compileInputFields(array $fields): array
    {
        $compiled = [];

        foreach ($fields as $field) {
            $compiled[$field->name] = [
                'type' => $this->parseTypeString($field->type),
                'description' => $field->description,
            ];

            if ($field->hasDefault) {
                $compiled[$field->name]['defaultValue'] = $field->defaultValue;
            }
        }

        return $compiled;
    }
}
