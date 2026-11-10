<?php

namespace Javaabu\Imports\Schema;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Javaabu\Imports\Columns\Column;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * The default columns of a model importer, worked out from the model's table
 *
 * Every table column except the primary key, timestamps, hidden attributes, JSON columns
 * and `imports.exclude` (passwords, tokens). Types come from the model's casts, then the
 * column type; NOT NULL columns without a default are required; `varchar(100)` gets
 * `max:100`. A foreign key such as `brand_id` with a `brand()` BelongsTo becomes a lookup
 * of the brand by name (or title, code, slug, email). The first single-column unique index
 * is the default key for finding existing records.
 */
class ModelSchema
{
    /** Columns of the related table tried, in order, to look up a related model */
    public const array LOOKUP_ATTRIBUTES = ['name', 'title', 'code', 'slug', 'email'];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected static array $columns = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    protected static array $indexes = [];

    public function __construct(
        protected Model $model,
    ) {}

    /**
     * @param  class-string<Model>|Model  $model
     */
    public static function for(string|Model $model): static
    {
        return new static(is_string($model) ? new $model : $model);
    }

    /**
     * @return array<int, Column>
     */
    public function columns(): array
    {
        $columns = [];

        foreach ($this->tableColumns() as $definition) {
            if ($column = $this->column($definition)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * The first single-column unique index among the attributes, as the default uniqueBy()
     *
     * @param  array<int, string>  $attributes
     * @return array<int, string>
     */
    public function uniqueBy(array $attributes): array
    {
        foreach ($this->indexes() as $index) {
            if (! ($index['unique'] ?? false) || ($index['primary'] ?? false)) {
                continue;
            }

            $columns = $index['columns'] ?? [];

            if (count($columns) === 1 && in_array($columns[0], $attributes, true)) {
                return $columns;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $definition  from Schema::getColumns()
     */
    protected function column(array $definition): ?Column
    {
        $name = $definition['name'];

        if ($this->isExcluded($name, $definition)) {
            return null;
        }

        $column = Column::make($name);

        if ($relation = $this->belongsTo($name)) {
            $related = $relation->getRelated();

            $column->lookup($related::class, $this->lookupAttributes($related), $relation->getOwnerKeyName());
        } else {
            $this->applyType($column, $name, $definition);
        }

        $required = ! ($definition['nullable'] ?? true)
            && ($definition['default'] ?? null) === null
            && ! ($definition['auto_increment'] ?? false);

        $column->required($required);

        if (! $column->getLookup() && preg_match('/^(?:var)?char\((\d+)\)/i', (string) ($definition['type'] ?? ''), $matches)) {
            $column->rules('max:' . $matches[1]);
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function isExcluded(string $name, array $definition): bool
    {
        if ($name === $this->model->getKeyName() || ($definition['auto_increment'] ?? false)) {
            return true;
        }

        $timestamps = [$this->model->getCreatedAtColumn(), $this->model->getUpdatedAtColumn(), 'deleted_at'];

        if (in_array($name, $timestamps, true)) {
            return true;
        }

        if (in_array($name, $this->model->getHidden(), true)) {
            return true;
        }

        if (in_array(strtolower((string) ($definition['type_name'] ?? '')), ['json', 'jsonb', 'blob', 'binary', 'varbinary', 'bytea'], true)) {
            return true;
        }

        foreach (config('imports.exclude', []) as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function applyType(Column $column, string $name, array $definition): void
    {
        $cast = $this->model->getCasts()[$name] ?? null;

        if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, BackedEnum::class)) {
            $column->enum($cast);

            return;
        }

        $cast = is_string($cast) ? strtolower(Str::before($cast, ':')) : null;

        $type = match ($cast) {
            'bool', 'boolean' => Column::BOOLEAN,
            'int', 'integer' => Column::INTEGER,
            'float', 'double', 'real', 'decimal' => Column::NUMBER,
            'date', 'immutable_date' => Column::DATE,
            'datetime', 'immutable_datetime', 'timestamp' => Column::DATETIME,
            default => null,
        };

        $type ??= $this->typeFromDefinition($definition);

        $column->type($type);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function typeFromDefinition(array $definition): ?string
    {
        $type_name = strtolower((string) ($definition['type_name'] ?? ''));
        $type = strtolower((string) ($definition['type'] ?? ''));

        if ($type === 'tinyint(1)' || in_array($type_name, ['bool', 'boolean'], true)) {
            return Column::BOOLEAN;
        }

        return match (true) {
            in_array($type_name, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'bigint', 'int2', 'int4', 'int8'], true) => Column::INTEGER,
            in_array($type_name, ['decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8'], true) => Column::NUMBER,
            $type_name === 'date' => Column::DATE,
            in_array($type_name, ['datetime', 'timestamp', 'timestamptz'], true) => Column::DATETIME,
            in_array($type_name, ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'string', 'bpchar'], true) => Column::STRING,
            default => null,
        };
    }

    /**
     * The BelongsTo relation for a foreign key: `brand_id` → `brand()`
     */
    protected function belongsTo(string $name): ?BelongsTo
    {
        if (! str_ends_with($name, '_id')) {
            return null;
        }

        $method = Str::camel(substr($name, 0, -3));

        if (! method_exists($this->model, $method)) {
            return null;
        }

        $reflection = new ReflectionMethod($this->model, $method);
        $return = $reflection->getReturnType();

        if ($reflection->getNumberOfRequiredParameters() > 0) {
            return null;
        }

        if ($return instanceof ReflectionNamedType && ! is_a($return->getName(), BelongsTo::class, true)) {
            return null;
        }

        try {
            $relation = $this->model->{$method}();
        } catch (Throwable) {
            return null;
        }

        if (! $relation instanceof BelongsTo || $relation->getForeignKeyName() !== $name) {
            return null;
        }

        return $relation;
    }

    /**
     * @return array<int, string>
     */
    protected function lookupAttributes(Model $related): array
    {
        $available = array_column(static::describe($related, 'columns'), 'name');
        $attributes = array_values(array_intersect(self::LOOKUP_ATTRIBUTES, $available));

        return $attributes ?: [$related->getKeyName()];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function tableColumns(): array
    {
        return static::describe($this->model, 'columns');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function indexes(): array
    {
        return static::describe($this->model, 'indexes');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function describe(Model $model, string $what): array
    {
        $key = $model->getConnectionName() . '|' . $model->getTable();
        $cache = $what === 'columns' ? 'columns' : 'indexes';

        if (! isset(static::${$cache}[$key])) {
            $schema = Schema::connection($model->getConnectionName());

            static::${$cache}[$key] = $what === 'columns'
                ? $schema->getColumns($model->getTable())
                : $schema->getIndexes($model->getTable());
        }

        return static::${$cache}[$key];
    }

    /**
     * Forget the table definitions, e.g. after a migration in tests
     */
    public static function flush(): void
    {
        static::$columns = [];
        static::$indexes = [];
    }
}
