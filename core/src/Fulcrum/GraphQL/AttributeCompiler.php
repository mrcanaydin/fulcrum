<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Fulcrum\GraphQL\Attributes\ObjectType;
use Fulcrum\GraphQL\Attributes\Field;
use Fulcrum\GraphQL\Attributes\Query;
use Fulcrum\GraphQL\Attributes\Mutation;
use Fulcrum\GraphQL\Attributes\Arg;
use Fulcrum\GraphQL\Attributes\Authenticated;

use Fulcrum\Auth\Attributes\RequiresAbility;

/**
 * Scans PHP classes for Fulcrum GraphQL Attributes and compiles them into
 * an array of TypeDef objects.
 */
class AttributeCompiler
{
    /**
     * Compile a list of class names into TypeDefs.
     *
     * @param array<int, string> $classes FQCNs to scan
     * @return array<int, TypeDef>
     */
    public function compile(array $classes): array
    {
        $typeDefs = [];

        foreach ($classes as $className) {
            if (!class_exists($className)) {
                continue;
            }

            $refClass = new ReflectionClass($className);

            if ($this->hasAttribute($refClass, ObjectType::class)) {
                $typeDefs[] = $this->compileObjectType($refClass);
            }

            // A class can also contain Queries/Mutations independently of being an ObjectType
            $queryFields    = $this->compileFields($refClass, Query::class);
            $mutationFields = $this->compileFields($refClass, Mutation::class);

            if (!empty($queryFields)) {
                $typeDefs[] = new TypeDef(
                    kind: TypeDef::KIND_QUERY,
                    name: 'Query', // Logical group name, SchemaCompiler will merge these
                    className: $className,
                    fields: $queryFields,
                );
            }

            if (!empty($mutationFields)) {
                $typeDefs[] = new TypeDef(
                    kind: TypeDef::KIND_MUTATION,
                    name: 'Mutation',
                    className: $className,
                    fields: $mutationFields,
                );
            }
        }

        return $typeDefs;
    }

    private function compileObjectType(ReflectionClass $refClass): TypeDef
    {
        /** @var ObjectType $attr */
        $attr = $this->getAttributeInstance($refClass, ObjectType::class);

        $name        = $attr->name ?: $refClass->getShortName();
        $description = $attr->description;
        $fields      = $this->compileFields($refClass, Field::class);

        return new TypeDef(
            kind: TypeDef::KIND_OBJECT,
            name: $name,
            className: $refClass->getName(),
            fields: $fields,
            description: $description,
        );
    }

    /**
     * @param ReflectionClass $refClass
     * @param string          $attributeClass The Attribute to look for (Field, Query, Mutation)
     * @return array<int, FieldDef>
     */
    private function compileFields(ReflectionClass $refClass, string $attributeClass): array
    {
        $fields = [];

        foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($this->hasAttribute($method, $attributeClass)) {
                $fields[] = $this->compileMethodField($method, $attributeClass);
            }
        }

        // Object Types can also have properties as fields
        if ($attributeClass === Field::class) {
            foreach ($refClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($this->hasAttribute($property, $attributeClass)) {
                    $fields[] = $this->compilePropertyField($property, $attributeClass);
                }
            }
        }

        return $fields;
    }

    private function compileMethodField(ReflectionMethod $method, string $attributeClass): FieldDef
    {
        /** @var Field|Query|Mutation $attr */
        $attr = $this->getAttributeInstance($method, $attributeClass);

        $name        = $attr->name ?: $method->getName();
        $type        = $attr->type;
        $description = $attr->description ?? '';
        
        // Try to infer type from return type if not explicitly provided
        if ($type === '' && $method->hasReturnType()) {
            $type = $this->inferGraphQLTypeFromPHPType($method->getReturnType());
            if ($attributeClass === Field::class && $attr->nullable === false) {
                 if (!str_ends_with($type, '!')) {
                     $type .= '!';
                 }
            }
        }

        if ($type === '') {
            $type = 'String'; // Fallback
        }

        $args          = $this->compileArgs($method);
        $authenticated = $this->hasAttribute($method, Authenticated::class);
        
        $requiredAbilities = [];
        foreach ($method->getAttributes(RequiresAbility::class) as $attrRef) {
            $requiredAbilities[] = $attrRef->newInstance()->ability;
        }

        return new FieldDef(
            name: $name,
            type: $type,
            methodName: $method->getName(),
            args: $args,
            description: $description,
            authenticated: $authenticated,
            requiredAbilities: $requiredAbilities,
        );
    }

    private function compilePropertyField(ReflectionProperty $property, string $attributeClass): FieldDef
    {
        /** @var Field $attr */
        $attr = $this->getAttributeInstance($property, $attributeClass);

        $name        = $attr->name ?: $property->getName();
        $type        = $attr->type;
        $description = $attr->description ?? '';

        if ($type === '' && $property->hasType()) {
            $type = $this->inferGraphQLTypeFromPHPType($property->getType());
            if ($attr->nullable === false && !str_ends_with($type, '!')) {
                $type .= '!';
            }
        }

        if ($type === '') {
            $type = 'String';
        }

        return new FieldDef(
            name: $name,
            type: $type,
            methodName: $property->getName(), // Reused for property name in resolver
            args: [],
            description: $description,
            authenticated: false, // Properties cannot have #[Authenticated] currently
        );
    }

    /**
     * @return array<int, ArgDef>
     */
    private function compileArgs(ReflectionMethod $method): array
    {
        $args = [];

        foreach ($method->getAttributes(Arg::class) as $reflectionAttribute) {
            /** @var Arg $attr */
            $attr = $reflectionAttribute->newInstance();
            
            $hasDefault = array_key_exists('defaultValue', $reflectionAttribute->getArguments());

            $args[] = new ArgDef(
                name: $attr->name,
                type: $attr->type,
                description: $attr->description,
                defaultValue: $attr->defaultValue,
                hasDefault: $hasDefault,
            );
        }

        return $args;
    }

    private function hasAttribute(ReflectionClass|ReflectionMethod|ReflectionProperty $ref, string $attributeClass): bool
    {
        return count($ref->getAttributes($attributeClass)) > 0;
    }

    private function getAttributeInstance(ReflectionClass|ReflectionMethod|ReflectionProperty $ref, string $attributeClass): mixed
    {
        $attributes = $ref->getAttributes($attributeClass);
        return $attributes[0]->newInstance();
    }

    private function inferGraphQLTypeFromPHPType(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            $phpType = $type->getName();
            $gqlType = match ($phpType) {
                'int'    => 'Int',
                'float'  => 'Float',
                'bool'   => 'Boolean',
                'string' => 'String',
                'array'  => '[String]', // Basic fallback, user should specify explicit #[Field(type: '[User!]!')]
                default  => 'String',
            };
            
            return $type->allowsNull() ? $gqlType : "{$gqlType}!";
        }
        
        return 'String';
    }
}
