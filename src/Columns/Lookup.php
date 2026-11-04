<?php

namespace Javaabu\Imports\Columns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Turns a value in the file (a brand's name or code) into a related model's key
 *
 * Values are looked up a chunk at a time with whereIn, and remembered (up to
 * `imports.lookup_cache` values), so a file with 100,000 rows and 20 brands makes a
 * handful of queries. Matching ignores case and surrounding whitespace.
 */
class Lookup
{
    /** @var array<string, mixed> normalized value => key, or false for no match */
    protected array $cache = [];

    protected bool|Closure $create_missing = false;

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $by
     */
    public function __construct(
        protected string $model,
        protected array $by,
        protected ?string $key = null,
    ) {}

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return $this->model;
    }

    /**
     * @return array<int, string>
     */
    public function by(): array
    {
        return $this->by;
    }

    public function keyName(): string
    {
        return $this->key ?? (new $this->model)->getKeyName();
    }

    public function createMissing(bool|Closure $create = true): static
    {
        $this->create_missing = $create;

        return $this;
    }

    public function createsMissing(): bool
    {
        return $this->create_missing !== false;
    }

    /**
     * Load the keys for values not seen yet, in one query per attribute
     *
     * @param  array<int, mixed>  $values
     */
    public function warm(array $values): void
    {
        $missing = [];

        foreach ($values as $value) {
            $normalized = $this->normalize($value);

            if ($normalized === '' || array_key_exists($normalized, $this->cache) || isset($missing[$normalized])) {
                continue;
            }

            $missing[$normalized] = is_string($value) ? trim($value) : $value;
        }

        if ($missing === []) {
            return;
        }

        $this->trimCache(count($missing));

        $key_name = $this->keyName();

        foreach ($this->by as $attribute) {
            $wanted = array_diff_key($missing, $this->cache);

            if ($wanted === []) {
                break;
            }

            $this->model::query()
                ->whereIn($attribute, array_values($wanted))
                ->toBase()
                ->get([$key_name, $attribute])
                ->each(function (object $row) use ($attribute, $key_name) {
                    $normalized = $this->normalize($row->{$attribute});

                    if (! array_key_exists($normalized, $this->cache)) {
                        $this->cache[$normalized] = $row->{$key_name};
                    }
                });
        }

        // Case-insensitive matches the database didn't find (on case-sensitive collations)
        // are left unresolved here and looked up one by one in find()
    }

    /**
     * The key for a value: null when the value is empty, false when nothing matches
     */
    public function find(mixed $value): mixed
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return null;
        }

        if (! array_key_exists($normalized, $this->cache)) {
            $this->cache[$normalized] = $this->query($value);
        }

        return $this->cache[$normalized];
    }

    /**
     * Create the related model for a value nothing matched, and remember its key
     */
    public function create(mixed $value): mixed
    {
        $attributes = $this->create_missing instanceof Closure
            ? ($this->create_missing)($value)
            : [$this->by[0] => is_string($value) ? trim($value) : $value];

        $model = $this->model::query()->create($attributes);
        $key = $model->getAttribute($this->keyName());

        $this->cache[$this->normalize($value)] = $key;

        return $key;
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    protected function query(mixed $value): mixed
    {
        $key_name = $this->keyName();
        $value = is_string($value) ? trim($value) : $value;

        foreach ($this->by as $attribute) {
            $query = $this->model::query();

            $key = is_string($value)
                ? $query->whereRaw('lower(' . $query->getQuery()->getGrammar()->wrap($attribute) . ') = ?', [Str::lower($value)])->value($key_name)
                : $query->where($attribute, $value)->value($key_name);

            if ($key !== null) {
                return $key;
            }
        }

        return false;
    }

    public function normalize(mixed $value): string
    {
        if ($value === null || is_array($value) || is_object($value) && ! method_exists($value, '__toString')) {
            return '';
        }

        return Str::lower(trim((string) $value));
    }

    protected function trimCache(int $incoming): void
    {
        $limit = max((int) config('imports.lookup_cache', 10000), 100);

        if (count($this->cache) + $incoming <= $limit) {
            return;
        }

        $this->cache = array_slice($this->cache, (int) floor($limit / 2), null, true);
    }
}
