<?php

declare(strict_types=1);

namespace Fulcrum\Database;

use Fulcrum\Database\Relations\BelongsTo;
use Fulcrum\Database\Relations\HasMany;
use Fulcrum\Support\Str;
use RuntimeException;

abstract class Model
{
    /** @var callable(): DatabaseManager|null */
    private static $databaseResolver = null;

    /** @var array<string, mixed> */
    protected array $relations = [];

    /** @param array<string, mixed> $attributes */
    public function __construct(protected array $attributes = []) {}

    /** @param callable(): DatabaseManager $resolver */
    public static function resolveDatabaseUsing(callable $resolver): void
    {
        self::$databaseResolver = $resolver;
    }

    public static function query(): ModelQueryBuilder
    {
        return new ModelQueryBuilder(static::class, static::database()->table(static::tableName()));
    }

    public static function find(int|string $id): ?Model
    {
        return static::query()->find($id);
    }

    /** @param array<string, mixed> $attributes */
    public static function create(array $attributes): Model
    {
        return static::query()->create($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public static function hydrate(array $attributes): Model
    {
        $class = static::class;

        return new $class($attributes);
    }

    public static function tableName(): string
    {
        $class = static::class;
        $model = new $class();

        if ($model->table() !== '') {
            return $model->table();
        }

        $parts = explode('\\', static::class);

        return Str::plural(Str::snake(end($parts) ?: 'model'));
    }

    public function table(): string
    {
        return property_exists($this, 'table') && is_string($this->table) ? $this->table : '';
    }

    public function primaryKey(): string
    {
        return property_exists($this, 'primaryKey') && is_string($this->primaryKey) ? $this->primaryKey : 'id';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->relations as $name => $value) {
            $attributes[$name] = $this->relationToArray($value);
        }

        return $attributes;
    }

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->attributes) || array_key_exists($key, $this->relations);
    }

    public function setRelation(string $name, mixed $value): void
    {
        $this->relations[$name] = $value;
    }

    public function getRelation(string $name): mixed
    {
        return $this->relations[$name] ?? null;
    }

    /**
     * @param class-string<Model> $related
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= Str::snake($this->modelBaseName()) . '_id';
        $localKey ??= $this->primaryKey();

        return new HasMany($related, $foreignKey, $localKey);
    }

    /**
     * @param class-string<Model> $related
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $relatedModel = new $related();
        $foreignKey ??= Str::snake($this->modelBaseName($related)) . '_id';
        $ownerKey ??= $relatedModel->primaryKey();

        return new BelongsTo($related, $foreignKey, $ownerKey);
    }

    protected static function database(): DatabaseManager
    {
        $manager = self::$databaseResolver !== null ? (self::$databaseResolver)() : null;

        if (!$manager instanceof DatabaseManager) {
            throw new RuntimeException('Database manager is not available for models.');
        }

        return $manager;
    }

    /** @param class-string<Model>|null $class */
    private function modelBaseName(?string $class = null): string
    {
        $parts = explode('\\', $class ?? static::class);

        return end($parts) ?: 'model';
    }

    private function relationToArray(mixed $value): mixed
    {
        if ($value instanceof Model) {
            return $value->toArray();
        }

        if (is_array($value)) {
            return array_map(
                fn (mixed $item): mixed => $item instanceof Model ? $item->toArray() : $item,
                $value
            );
        }

        return $value;
    }
}
