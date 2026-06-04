<?php

declare(strict_types=1);

namespace Fulcrum\GraphQL;

use Fulcrum\Database\ModelCreator;
use Fulcrum\Support\Str;
use InvalidArgumentException;

class ResourceCreator
{
    /**
     * @param list<string> $fields
     * @return list<string>
     */
    public function create(string $basePath, string $name, array $fields): array
    {
        $model = (new ModelCreator())->className($name);
        $resource = Str::camel($model);
        $resources = Str::plural($resource);
        $modelPath = $basePath . '/src/Models';
        $graphqlPath = $basePath . '/src/GraphQL';
        $created = [];

        if (!is_dir($graphqlPath)) {
            mkdir($graphqlPath, 0777, true);
        }

        $modelFile = $modelPath . '/' . $model . '.php';
        if (!file_exists($modelFile)) {
            $created[] = (new ModelCreator())->create($modelPath, $model);
        }

        $fieldDefs = $this->fields($fields);
        $files = [
            $graphqlPath . '/' . $model . 'Type.php' => $this->typeStub($model, $fieldDefs),
            $graphqlPath . '/' . $model . 'Query.php' => $this->queryStub($model, $resource, $resources),
            $graphqlPath . '/' . $model . 'Mutation.php' => $this->mutationStub($model, $resource, $fieldDefs),
        ];

        foreach ($files as $file => $contents) {
            if (file_exists($file)) {
                throw new InvalidArgumentException("Resource file already exists: {$file}");
            }

            file_put_contents($file, $contents);
            $created[] = $file;
        }

        return $created;
    }

    /**
     * @param list<string> $fields
     * @return list<array{name: string, php: string, graphql: string, validation: string}>
     */
    private function fields(array $fields): array
    {
        $parsed = [];

        foreach ($fields as $field) {
            [$name, $type] = array_pad(explode(':', $field, 2), 2, 'string');

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new InvalidArgumentException("Invalid resource field [{$name}].");
            }

            $parsed[] = [
                'name' => $name,
                'php' => $this->phpType($type),
                'graphql' => $this->graphqlType($type),
                'validation' => $this->validationType($type),
            ];
        }

        return $parsed;
    }

    /** @param list<array{name: string, php: string, graphql: string, validation: string}> $fields */
    private function typeStub(string $model, array $fields): string
    {
        $properties = '';
        foreach ($fields as $field) {
            $properties .= "\n    #[Field(type: '{$field['graphql']}!')]\n";
            $properties .= "    public {$field['php']} \${$field['name']};\n";
        }

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\GraphQL;

use Fulcrum\\GraphQL\\Attributes\\Field;
use Fulcrum\\GraphQL\\Attributes\\ObjectType;

#[ObjectType(name: '{$model}')]
class {$model}Type
{
    #[Field(type: 'ID!')]
    public string \$id;
{$properties}

    #[Field(type: 'String')]
    public ?string \$created_at = null;

    #[Field(type: 'String')]
    public ?string \$updated_at = null;
}
PHP;
    }

    private function queryStub(string $model, string $resource, string $resources): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\GraphQL;

use App\\Models\\{$model};
use Fulcrum\\GraphQL\\Attributes\\Arg;
use Fulcrum\\GraphQL\\Attributes\\Query;

class {$model}Query
{
    #[Query(name: '{$resource}', type: '{$model}')]
    #[Arg(name: 'id', type: 'ID!')]
    public function {$resource}(mixed \$root, array \$args): ?array
    {
        return {$model}::find((string) \$args['id'])?->toArray();
    }

    #[Query(name: '{$resources}', type: '[{$model}!]!')]
    #[Arg(name: 'limit', type: 'Int', defaultValue: 25)]
    public function {$resources}(mixed \$root, array \$args): array
    {
        return {$model}::query()
            ->latest()
            ->limit(max(1, min((int) (\$args['limit'] ?? 25), 100)))
            ->toArray();
    }
}
PHP;
    }

    /** @param list<array{name: string, php: string, graphql: string, validation: string}> $fields */
    private function mutationStub(string $model, string $resource, array $fields): string
    {
        $createArgs = '';
        $updateArgs = "    #[Arg(name: 'id', type: 'ID!')]\n";
        $rules = [];
        $sanitizers = [];
        $createData = [];
        $updateData = [];

        foreach ($fields as $field) {
            $createArgs .= "    #[Arg(name: '{$field['name']}', type: '{$field['graphql']}!')]\n";
            $updateArgs .= "    #[Arg(name: '{$field['name']}', type: '{$field['graphql']}')]\n";
            $rules[] = "                '{$field['name']}' => 'required|{$field['validation']}',";
            $sanitizers[] = $field['validation'] === 'string'
                ? "                '{$field['name']}' => ['trim', 'strip_tags'],"
                : "                '{$field['name']}' => [],";
            $createData[] = "            '{$field['name']}' => \$input['{$field['name']}'],";
            $updateData[] = "        if (array_key_exists('{$field['name']}', \$args)) {\n            \$data['{$field['name']}'] = \$args['{$field['name']}'];\n        }";
        }

        $createRules = implode("\n", $rules);
        $createSanitizers = implode("\n", $sanitizers);
        $createPayload = implode("\n", $createData);
        $updatePayload = implode("\n\n", $updateData);
        $createMethod = 'create' . $model;
        $updateMethod = 'update' . $model;
        $deleteMethod = 'delete' . $model;

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\\GraphQL;

use App\\Models\\{$model};
use Fulcrum\\GraphQL\\Attributes\\Arg;
use Fulcrum\\GraphQL\\Attributes\\Mutation;
use Fulcrum\\Validation\\Validator;

class {$model}Mutation
{
    public function __construct(private readonly Validator \$validator) {}

    #[Mutation(name: '{$createMethod}', type: '{$model}!')]
{$createArgs}    public function {$createMethod}(mixed \$root, array \$args): array
    {
        \$input = \$this->validator->validate(
            \$args,
            [
{$createRules}
            ],
            [
{$createSanitizers}
            ]
        );

        \$now = gmdate('Y-m-d H:i:s');

        return {$model}::create([
{$createPayload}
            'created_at' => \$now,
            'updated_at' => \$now,
        ])->toArray();
    }

    #[Mutation(name: '{$updateMethod}', type: '{$model}')]
{$updateArgs}    public function {$updateMethod}(mixed \$root, array \$args): ?array
    {
        \$model = {$model}::find((string) \$args['id']);

        if (\$model === null) {
            return null;
        }

        \$data = [];
{$updatePayload}

        if (\$data === []) {
            return \$model->toArray();
        }

        \$data['updated_at'] = gmdate('Y-m-d H:i:s');

        {$model}::query()->where('id', (string) \$args['id'])->update(\$data);

        return {$model}::find((string) \$args['id'])?->toArray();
    }

    #[Mutation(name: '{$deleteMethod}', type: 'Boolean!')]
    #[Arg(name: 'id', type: 'ID!')]
    public function {$deleteMethod}(mixed \$root, array \$args): bool
    {
        return {$model}::query()->where('id', (string) \$args['id'])->delete() > 0;
    }
}
PHP;
    }

    private function phpType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'id' => 'int',
            'float', 'decimal' => 'float',
            'bool', 'boolean' => 'bool',
            default => 'string',
        };
    }

    private function graphqlType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'Int',
            'float', 'decimal' => 'Float',
            'bool', 'boolean' => 'Boolean',
            'id' => 'ID',
            default => 'String',
        };
    }

    private function validationType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer', 'id' => 'integer',
            'float', 'decimal' => 'numeric',
            'bool', 'boolean' => 'boolean',
            default => 'string',
        };
    }
}
