<?php

namespace Javaabu\Imports;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Enums\Duplicates;
use Javaabu\Imports\Enums\RowOutcomes;
use Javaabu\Imports\Exceptions\RowFailedException;
use Javaabu\Imports\Schema\ModelSchema;

/**
 * Imports rows as Eloquent models
 *
 * ```php
 * class ProductsImporter extends ModelImporter
 * {
 *     public function modelClass(): string
 *     {
 *         return Product::class;
 *     }
 * }
 * ```
 *
 * Without columns(), the columns come from the model's table (see ModelSchema). Rows that
 * match an existing model by uniqueBy() are skipped, updated or failed, as duplicates()
 * says. Models are saved one at a time with save(), so casts, mutators, events and
 * observers all run.
 */
abstract class ModelImporter extends Importer
{
    /** @var array<string, Model|null> existing models of the current chunk by unique key; null when there's none */
    protected array $existing = [];

    /** @var array<int, string>|null */
    protected ?array $unique_keys = null;

    /**
     * @return class-string<Model>
     */
    abstract public function modelClass(): string;

    /**
     * @return array<int, Column|string>
     */
    public function columns(): array
    {
        return ModelSchema::for($this->modelClass())->columns();
    }

    /**
     * The attributes that identify an existing model, e.g. `['sku']` or `['email']`.
     * Defaults to the first single-column unique index of the table that's a column here.
     * Return `[]` to always create new models.
     *
     * @return array<int, string>
     */
    public function uniqueBy(): array
    {
        return ModelSchema::for($this->modelClass())->uniqueBy(array_keys($this->resolvedColumns()));
    }

    /**
     * Attribute values for new models that aren't in the file: `['status' => 'draft']`.
     * Closures get the row's data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function defaults(array $data): array
    {
        return [];
    }

    public function label(): string
    {
        $model = $this->modelClass();

        return __(Str::headline(Str::pluralStudly(class_basename($model))));
    }

    /**
     * Who may import: anyone who may create the model (`create` in its policy)
     */
    public function authorize(?Model $user): bool
    {
        return (bool) $user?->can('create', $this->modelClass());
    }

    public function newModel(): Model
    {
        $class = $this->modelClass();

        return new $class;
    }

    /**
     * Load the existing models that the chunk's rows match, in one query
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function beforePersist(array $rows): void
    {
        $this->existing = [];
        $keys = $this->uniqueKeys();

        if ($keys === []) {
            return;
        }

        $first = $keys[0];
        $values = [];

        foreach ($rows as $data) {
            if (($data[$first] ?? null) !== null) {
                $values[] = $data[$first];
            }
        }

        if ($values === []) {
            return;
        }

        $this->existingQuery()
            ->whereIn($first, array_values(array_unique($values, SORT_REGULAR)))
            ->get()
            ->each(function (Model $model) {
                $key = $this->keyFor($model->getAttributes());

                if ($key !== null) {
                    $this->existing[$key] ??= $model;
                }
            });

        foreach ($rows as $data) {
            $key = $this->keyFor($data);

            if ($key !== null && ! array_key_exists($key, $this->existing)) {
                $this->existing[$key] = null;
            }
        }
    }

    /**
     * The query existing models are found with. Narrow it to scope imports, e.g. to a tenant.
     */
    public function existingQuery(): Builder
    {
        return $this->modelClass()::query();
    }

    /**
     * The existing model a row matches, if any
     *
     * @param  array<string, mixed>  $data
     */
    public function existingModel(array $data): ?Model
    {
        $key = $this->keyFor($data);

        if ($key === null) {
            return null;
        }

        if (array_key_exists($key, $this->existing)) {
            return $this->existing[$key];
        }

        $query = $this->existingQuery();

        foreach ($this->uniqueKeys() as $attribute) {
            $query->where($attribute, $data[$attribute]);
        }

        return $this->existing[$key] = $query->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function previewOutcome(array $data, int $row): RowOutcomes
    {
        $existing = $this->existingModel($data);

        if (! $existing) {
            // Later rows with the same key would update this one
            if (($key = $this->keyFor($data)) !== null) {
                $this->existing[$key] = $this->newModel();
            }

            return RowOutcomes::CREATED;
        }

        return match ($this->getDuplicates()) {
            Duplicates::UPDATE => RowOutcomes::UPDATED,
            Duplicates::SKIP => RowOutcomes::SKIPPED,
            Duplicates::FAIL => throw RowFailedException::withMessage(__('A matching record already exists.')),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function persist(array $data, int $row): RowOutcomes
    {
        $model = $this->existingModel($data);
        $exists = $model !== null;

        if ($exists && $this->getDuplicates() === Duplicates::SKIP) {
            return RowOutcomes::SKIPPED;
        }

        if ($exists && $this->getDuplicates() === Duplicates::FAIL) {
            throw RowFailedException::withMessage(__('A matching record already exists.'));
        }

        $model ??= $this->newModel();

        $this->fill($model, $data, $exists);
        $this->beforeSave($model, $data, $row);
        $model->save();
        $this->afterSave($model, $data, $row);

        if (($key = $this->keyFor($data)) !== null) {
            $this->existing[$key] = $model;
        }

        return $exists ? RowOutcomes::UPDATED : RowOutcomes::CREATED;
    }

    /**
     * Set the row's values on the model. New models also get defaults(). Empty cells leave
     * the attribute alone (the table's default for new models, the existing value when
     * updating) unless the column clearsWhenEmpty().
     *
     * @param  array<string, mixed>  $data
     */
    protected function fill(Model $model, array $data, bool $exists): void
    {
        if (! $exists) {
            foreach ($this->defaults($data) as $attribute => $value) {
                $model->setAttribute($attribute, $value instanceof Closure ? $value($data) : $value);
            }
        }

        foreach ($this->resolvedColumns() as $attribute => $column) {
            if ($column->isVirtual() || ! array_key_exists($attribute, $data)) {
                continue;
            }

            if ($data[$attribute] === null && ! $column->shouldClearWhenEmpty()) {
                continue;
            }

            $model->setAttribute($attribute, $data[$attribute]);
        }
    }

    /**
     * Called before a model is saved, new or existing
     *
     * @param  array<string, mixed>  $data
     */
    protected function beforeSave(Model $model, array $data, int $row): void {}

    /**
     * Called after a model is saved, e.g. to sync relations
     *
     * @param  array<string, mixed>  $data
     */
    protected function afterSave(Model $model, array $data, int $row): void {}

    /**
     * uniqueBy(), worked out once per run
     *
     * @return array<int, string>
     */
    protected function uniqueKeys(): array
    {
        return $this->unique_keys ??= $this->uniqueBy();
    }

    public function reset(): void
    {
        parent::reset();

        $this->existing = [];
        $this->unique_keys = null;
    }

    /**
     * @return array<int, string>
     */
    protected function transientProperties(): array
    {
        return [...parent::transientProperties(), 'existing', 'unique_keys'];
    }

    /**
     * A row's (or model's) unique key as one string, ignoring case; null when it has no key
     *
     * @param  array<string, mixed>  $values
     */
    protected function keyFor(array $values): ?string
    {
        $keys = $this->uniqueKeys();

        if ($keys === []) {
            return null;
        }

        $parts = [];

        foreach ($keys as $attribute) {
            $value = $values[$attribute] ?? null;

            if ($value === null || $value === '') {
                return null;
            }

            $parts[] = Str::lower(trim($value instanceof BackedEnum ? (string) $value->value : (string) $value));
        }

        return implode("\u{1F}", $parts);
    }
}
