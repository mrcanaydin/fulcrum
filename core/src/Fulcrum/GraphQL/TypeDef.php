<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

/**
 * Intermediate representation of a compiled GraphQL type.
 *
 * AttributeCompiler produces TypeDef objects; SchemaCompiler consumes them
 * to build a webonyx Schema.
 */
final class TypeDef
{
    public const KIND_OBJECT   = 'object';
    public const KIND_QUERY    = 'query';
    public const KIND_MUTATION = 'mutation';

    /**
     * @param string                    $kind      One of the KIND_* constants
     * @param string                    $name      GraphQL type/field name
     * @param string                    $className FQCN of the resolver/type class
     * @param array<int, FieldDef>      $fields
     * @param string                    $description
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly string $className,
        public readonly array  $fields      = [],
        public readonly string $description = '',
    ) {}
}
