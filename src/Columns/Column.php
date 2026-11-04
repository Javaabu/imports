<?php

namespace Javaabu\Imports\Columns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Validation\Rule as LegacyRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use InvalidArgumentException;

/**
 * One field an importer fills: where its value comes from, how it's read, checked and saved
 *
 * ```php
 * Column::make('sku')->required()->rules('string|max:50')->aliases(['code']);
 * Column::make('price')->number()->rules('min:0')->default(0);
 * Column::make('brand_id')->lookup(Brand::class, ['name', 'code']);
 * Column::make('status')->enum(ProductStatuses::class);
 * ```
 */
class Column
{
    public const string BOOLEAN = 'boolean';

    public const string INTEGER = 'integer';

    public const string NUMBER = 'number';

    public const string DATE = 'date';

    public const string DATETIME = 'datetime';

    public const string STRING = 'string';

    protected string|Closure|null $label = null;

    protected ?string $description = null;

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected ?bool $required = null;

    /** @var array<int, string> */
    protected array $aliases = [];

    protected mixed $default = null;

    protected bool $has_default = false;

    protected ?Closure $transform = null;

    protected ?string $type = null;

    /** @var class-string<BackedEnum>|null */
    protected ?string $enum = null;

    protected ?Lookup $lookup = null;

    protected bool $virtual = false;

    protected bool $clears_when_empty = false;

    protected bool $trim = true;

    protected mixed $example = null;

    final public function __construct(
        protected string $attribute,
    ) {
        if ($attribute === '') {
            throw new InvalidArgumentException('A column needs an attribute name.');
        }
    }

    public static function make(string $attribute): static
    {
        return new static($attribute);
    }

    /**
     * The attribute the value is saved to (and the key in the row data)
     */
    public function attribute(): string
    {
        return $this->attribute;
    }

    /**
     * The heading people see, in templates, the mapping step and error messages.
     * A closure is called when the label is needed, so it can be translated then.
     */
    public function label(string|Closure $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The label, translated: `role_id` → `Role`, `released_on` → `Released On`
     */
    public function getLabel(): string
    {
        if ($this->label instanceof Closure) {
            return (string) ($this->label)();
        }

        if ($this->label !== null) {
            return __($this->label);
        }

        return __(Str::headline($this->baseName()));
    }

    /**
     * Help text shown next to the column in the mapping step
     */
    public function description(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description === null ? null : __($this->description);
    }

    /**
     * Validation rules for the value, added to any the column's type implies
     *
     * @param  string|array<int, mixed>|ValidationRule|LegacyRule  $rules
     */
    public function rules(string|array|ValidationRule|LegacyRule $rules): static
    {
        $rules = is_string($rules) ? explode('|', $rules) : Arr::wrap($rules);

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                $this->required = true;

                continue;
            }

            if ($rule === 'nullable') {
                $this->required ??= false;

                continue;
            }

            $this->rules[] = $rule;
        }

        return $this;
    }

    /**
     * Every row must have a value (after defaults are applied). Required columns must be
     * mapped before an import can run.
     */
    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function nullable(): static
    {
        return $this->required(false);
    }

    public function isRequired(): bool
    {
        return (bool) $this->required;
    }

    /**
     * Whether required() or nullable() was called (or 'required'/'nullable' given as a rule)
     */
    public function requiredIsSet(): bool
    {
        return $this->required !== null;
    }

    /**
     * The full rule list: required or nullable, the type's rules, then the column's own
     *
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        $rules = [$this->isRequired() ? 'required' : 'nullable'];

        if ($this->lookup) {
            return [...$rules, ...$this->rules];
        }

        $type_rules = match ($this->type) {
            self::BOOLEAN => ['boolean'],
            self::INTEGER => ['integer'],
            self::NUMBER => ['numeric'],
            self::DATE, self::DATETIME => ['date'],
            self::STRING => ['string'],
            default => [],
        };

        if ($this->enum) {
            $type_rules[] = new Enum($this->enum);
        }

        return [...$rules, ...$type_rules, ...$this->rules];
    }

    /**
     * Other headings that should map to this column automatically, e.g. `['code', 'item code']`
     *
     * @param  array<int, string>|string  $aliases
     */
    public function aliases(array|string $aliases): static
    {
        $this->aliases = [...$this->aliases, ...Arr::wrap($aliases)];

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * The value used when the cell is empty or the column isn't mapped. A closure gets the row.
     */
    public function default(mixed $value): static
    {
        $this->default = $value;
        $this->has_default = true;

        return $this;
    }

    public function hasDefault(): bool
    {
        return $this->has_default;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function getDefault(array $row = []): mixed
    {
        return $this->default instanceof Closure ? ($this->default)($row) : $this->default;
    }

    /**
     * Change the value before it's validated: `fn ($value, array $row) => strtoupper($value)`.
     * Runs after the type conversion and lookups, and only for values that aren't empty.
     */
    public function transform(Closure $callback): static
    {
        $this->transform = $callback;

        return $this;
    }

    public function getTransform(): ?Closure
    {
        return $this->transform;
    }

    /**
     * Read the value as a type: boolean, integer, number, date, datetime or string
     */
    public function type(?string $type): static
    {
        if ($type !== null && ! in_array($type, [self::BOOLEAN, self::INTEGER, self::NUMBER, self::DATE, self::DATETIME, self::STRING], true)) {
            throw new InvalidArgumentException("Unknown column type [{$type}].");
        }

        $this->type = $type;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Yes/No, True/False, 1/0 (and their translations) become booleans
     */
    public function boolean(): static
    {
        return $this->type(self::BOOLEAN);
    }

    public function integer(): static
    {
        return $this->type(self::INTEGER);
    }

    /**
     * Numbers, with thousands separators allowed: `1,250.50`
     */
    public function number(): static
    {
        return $this->type(self::NUMBER);
    }

    /**
     * Dates from date cells, Excel serial numbers or text Carbon can parse
     */
    public function date(): static
    {
        return $this->type(self::DATE);
    }

    public function datetime(): static
    {
        return $this->type(self::DATETIME);
    }

    public function string(): static
    {
        return $this->type(self::STRING);
    }

    /**
     * Accept an enum case by its value or its label (`label()` method), ignoring case
     *
     * @param  class-string<BackedEnum>  $enum
     */
    public function enum(string $enum): static
    {
        if (! is_subclass_of($enum, BackedEnum::class)) {
            throw new InvalidArgumentException("[{$enum}] is not a backed enum.");
        }

        $this->enum = $enum;

        return $this;
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    public function getEnum(): ?string
    {
        return $this->enum;
    }

    /**
     * Find a related model by one or more of its attributes and save its key, e.g. a brand
     * by name or code. Values that match nothing fail validation, unless createMissing().
     *
     * @param  class-string<Model>  $model
     * @param  string|array<int, string>  $by
     */
    public function lookup(string $model, string|array $by = 'name', ?string $key = null): static
    {
        $this->lookup = new Lookup($model, Arr::wrap($by), $key);

        return $this;
    }

    /**
     * Create the related model when a lookup finds nothing. The closure gets the value and
     * returns the attributes; by default the first `by` attribute is set to the value.
     */
    public function createMissing(bool|Closure $create = true): static
    {
        if (! $this->lookup) {
            throw new InvalidArgumentException('Call lookup() before createMissing().');
        }

        $this->lookup->createMissing($create);

        return $this;
    }

    public function getLookup(): ?Lookup
    {
        return $this->lookup;
    }

    /**
     * Validated and passed to the importer's hooks, but not set on the model
     */
    public function virtual(bool $virtual = true): static
    {
        $this->virtual = $virtual;

        return $this;
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    /**
     * When updating an existing model, an empty cell clears the attribute. By default empty
     * cells leave existing values alone.
     */
    public function clearsWhenEmpty(bool $clears = true): static
    {
        $this->clears_when_empty = $clears;

        return $this;
    }

    public function shouldClearWhenEmpty(): bool
    {
        return $this->clears_when_empty;
    }

    /**
     * Keep surrounding whitespace in text values (they're trimmed by default)
     */
    public function withoutTrimming(): static
    {
        $this->trim = false;

        return $this;
    }

    public function shouldTrim(): bool
    {
        return $this->trim;
    }

    /**
     * A sample value for the import template
     */
    public function example(mixed $value): static
    {
        $this->example = $value;

        return $this;
    }

    public function getExample(): mixed
    {
        return $this->example instanceof Closure ? ($this->example)() : $this->example;
    }

    /**
     * The attribute without a foreign key's `_id`, for labels and matching headings
     */
    public function baseName(): string
    {
        if ($this->lookup && str_ends_with($this->attribute, '_id')) {
            return substr($this->attribute, 0, -3);
        }

        return $this->attribute;
    }

    /**
     * What the mapping step and the API show about the column
     *
     * @return array{attribute: string, label: string, description: string|null, required: bool, aliases: array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'label' => $this->getLabel(),
            'description' => $this->getDescription(),
            'required' => $this->isRequired(),
            'aliases' => $this->aliases,
        ];
    }
}
