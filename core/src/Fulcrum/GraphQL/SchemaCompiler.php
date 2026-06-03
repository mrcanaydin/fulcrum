<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Fulcrum\Container\Contracts\ContainerInterface;

/**
 * Converts an array of TypeDef IR objects into a webonyx GraphQL Schema.
 */
class SchemaCompiler
{
    /** @var array<string, Type> */
    private array $typeCache = [];

    /** @var array<string, TypeDef> */
    private array $objectDefs = [];

    public function __construct(
        private readonly ContainerInterface $container
    ) {}

    /**
     * @param array<int, TypeDef> $typeDefs
     */
    public function compile(array $typeDefs): Schema
    {
        $queryFields    = [];
        $mutationFields = [];

        // 1. First pass: Register object definitions
        foreach ($typeDefs as $def) {
            if ($def->kind === TypeDef::KIND_OBJECT) {
                $this->objectDefs[$def->name] = $def;
            }
        }

        // 2. Second pass: Collect Query/Mutation fields (which may depend on registered objects)
        foreach ($typeDefs as $def) {
            if ($def->kind === TypeDef::KIND_QUERY) {
                $queryFields = array_merge($queryFields, $this->compileFields($def->fields, $def->className));
            } elseif ($def->kind === TypeDef::KIND_MUTATION) {
                $mutationFields = array_merge($mutationFields, $this->compileFields($def->fields, $def->className));
            }
        }

        // 2. Build root Query type
        $queryType = null;
        if (!empty($queryFields)) {
            $queryType = new ObjectType([
                'name'   => 'Query',
                'fields' => $queryFields,
            ]);
        }

        // 3. Build root Mutation type
        $mutationType = null;
        if (!empty($mutationFields)) {
            $mutationType = new ObjectType([
                'name'   => 'Mutation',
                'fields' => $mutationFields,
            ]);
        }

        // 4. Return the Schema
        return new Schema([
            'query'    => $queryType,
            'mutation' => $mutationType,
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
                'args'        => $compiledArgs,
                'resolve'     => $this->createResolver($className, $field->methodName, $field->authenticated, $field->requiredAbilities),
            ];
        }

        return $compiled;
    }

    private function createResolver(string $className, string $methodName, bool $authenticated, array $requiredAbilities = []): callable
    {
        return function ($root, array $args, RequestContext $context) use ($className, $methodName, $authenticated, $requiredAbilities) {
            if ($authenticated && !$context->isAuth()) {
                throw new \Exception('Unauthenticated.');
            }

            if (!empty($requiredAbilities) && $context->isAuth()) {
                $user = $context->user();
                $tokenAbilities = $user['_token']['abilities'] ?? [];
                
                if (!in_array('*', $tokenAbilities)) {
                    foreach ($requiredAbilities as $ability) {
                        if (!in_array($ability, $tokenAbilities)) {
                            throw new \Exception("Missing required ability: {$ability}");
                        }
                    }
                }
            }

            // Simple property access on root object if it's an array or object and method isn't a resolver on a specific class
            // Actually, if it's an ObjectType field, we might just want to resolve properties.
            // But here $className is the class where the attribute was defined.
            // If the root object IS an instance of $className, we can call it directly.
            // If the field is on Query/Mutation, we resolve $className via container.

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
    }

    private function loadType(string $name): Type
    {
        if (isset($this->typeCache[$name])) {
            return $this->typeCache[$name];
        }

        // Check if it's a defined ObjectType
        if (isset($this->objectDefs[$name])) {
            $def = $this->objectDefs[$name];
            
            $this->typeCache[$name] = new ObjectType([
                'name'        => $def->name,
                'description' => $def->description,
                'fields'      => fn() => $this->compileFields($def->fields, $def->className),
            ]);

            return $this->typeCache[$name];
        }

        throw new \Exception("Unknown GraphQL type: {$name}");
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
}
