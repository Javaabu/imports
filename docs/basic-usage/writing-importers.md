---
title: Writing importers
sidebar_position: 1
---

An importer describes what a spreadsheet's rows become. Model importers extend `Javaabu\Imports\ModelImporter`; anything else extends `Javaabu\Imports\Importer`.

```bash
php artisan imports:make ProductsImporter --model=Product
```

writes `app/Imports/ProductsImporter.php` with the columns worked out from the `products` table, ready to adjust.

# The smallest importer

```php
namespace App\Imports;

use App\Models\Product;
use Javaabu\Imports\ModelImporter;

class ProductsImporter extends ModelImporter
{
    public function modelClass(): string
    {
        return Product::class;
    }
}
```

Without `columns()`, the columns come from the table:

- every column except the primary key, `created_at`, `updated_at`, `deleted_at`, the model's `$hidden` attributes, JSON and binary columns, and `imports.exclude` (`password`, `*_token`, `*_secret`, …);
- the type from the model's casts (enums, booleans, integers, decimals, dates), else the column type;
- `required` for `NOT NULL` columns without a default, and `max:N` for `varchar(N)`;
- a foreign key such as `brand_id` with a `brand()` `BelongsTo` becomes a [lookup](#lookups) of the brand by `name` (or `title`, `code`, `slug`, `email`, whichever the brands table has);
- the first single-column unique index (such as `sku` or `email`) finds [existing records](#existing-records).

Files written by [javaabu/exports](https://github.com/Javaabu/exports) from the same model import back as they are: its headings match, and enum labels, "Yes"/"No" and related names are read back.

# Columns

```php
use App\Enums\ProductStatuses;
use App\Models\Brand;
use Javaabu\Imports\Columns\Column;

public function columns(): array
{
    return [
        Column::make('sku')
            ->label(__('SKU'))
            ->required()
            ->rules('string|max:50')
            ->aliases(['code', 'item code'])
            ->example('TV-001'),
        Column::make('name')->required()->string()->rules('max:255'),
        Column::make('price')->number()->rules('min:0')->default(0),
        Column::make('stock')->integer(),
        Column::make('status')->enum(ProductStatuses::class)->default(ProductStatuses::DRAFT->value),
        Column::make('is_active')->label(__('Active'))->boolean(),
        Column::make('released_on')->date(),
        Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, ['name', 'code']),
        'description', // a plain attribute
    ];
}
```

| Method | What it does |
| --- | --- |
| `label(string\|Closure)` | The heading in templates, the mapping step and messages. Translated with `__()`. Default: the attribute as a headline (`brand_id` → `Brand`). |
| `description(string)` | Help text on the mapping step |
| `required()`, `nullable()` | A required column must be mapped before the import can run, and every row needs a value |
| `rules(string\|array\|Rule)` | Laravel validation rules, added after the type's rule |
| `aliases(array)` | Other headings that map to this column automatically |
| `default(mixed\|Closure)` | The value when the cell is empty or the column isn't in the file. A closure gets the row's data so far. |
| `transform(Closure)` | Change a non-empty value before validation: `fn ($value, array $data) => strtoupper($value)` |
| `boolean()`, `integer()`, `number()`, `date()`, `datetime()`, `string()` | Read the cell as a type (see below) |
| `enum(class-string)` | A backed enum, by value, case name or `label()`, ignoring case |
| `lookup(Model::class, by, key)` | A related model's key, found by one or more attributes |
| `createMissing(bool\|Closure)` | Create the related model when a lookup finds nothing |
| `virtual()` | Validated and passed to your hooks, but not set on the model |
| `clearsWhenEmpty()` | On update, an empty cell sets the attribute to null (by default it's left alone) |
| `withoutTrimming()` | Keep surrounding spaces in text |
| `example(mixed)` | The value in the template's example row |

Types read what people type in spreadsheets:

| Type | Accepts |
| --- | --- |
| `boolean()` | Yes/No, True/False, Y/N, On/Off, 1/0 and the translated Yes/No (`އާނ`, `ނޫން`) |
| `integer()`, `number()` | Numbers, with thousands separators: `1,250.50` |
| `date()`, `datetime()` | Date cells, Excel serial numbers and text Carbon can parse; saved as `Y-m-d` / `Y-m-d H:i:s` |
| `enum()` | The value, the case name or the label |
| `string()` | Text; numbers become text (`7701234`) |

A value that can't be read keeps its text, and the type's rule fails with Laravel's message, such as "The Price field must be a number."

Rules across columns go in `rules()`; messages in `messages()`:

```php
public function rules(): array
{
    return [
        'phone' => ['required_without:email'],
    ];
}

public function messages(): array
{
    return [
        'sku.max' => __('SKUs are at most 50 characters.'),
    ];
}
```

# Lookups

```php
Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, ['name', 'code']);
Column::make('category_id')->label(__('Category'))->lookup(Category::class, 'slug')->createMissing();
Column::make('island_id')->lookup(Island::class, 'name')->createMissing(fn (string $name) => ['name' => $name, 'atoll' => 'K']);
```

A lookup turns "Sony" or "SNY" into the brand's id. Values are matched ignoring case and spaces, a chunk at a time with one `whereIn` query per attribute, and remembered (up to `imports.lookup_cache` values), so 100,000 rows with 20 brands make a handful of queries. A value that matches nothing fails the row with `No Brand matches "Nokia".`, unless `createMissing()`. Prefer lookups to `exists:` rules, which run a query for every row.

# Existing records

```php
public function uniqueBy(): array
{
    return ['sku'];          // or ['store_id', 'sku']; [] always creates
}
```

A row with the same key as an existing model is skipped, updated or failed, as the import says:

```php
use Javaabu\Imports\Enums\Duplicates;

ProductsImporter::make()->duplicates(Duplicates::UPDATE)->import($path); // or 'skip' (default) or 'fail'
```

When a file has the same key twice, the second row updates (or skips) what the first created. Keys are compared ignoring case within a file; whether `abc` finds an existing `ABC` depends on your database's collation (MySQL's default ignores case, SQLite and PostgreSQL don't). Narrow which records count as existing with `existingQuery()`, or replace the lookup with `existingModel(array $data)`.

When updating, empty cells leave values alone, so a file with only `SKU` and `Price` updates prices. New models get the table's defaults for empty cells.

# Hooks

```php
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Javaabu\Imports\Exceptions\RowFailedException;

class ProductsImporter extends ModelImporter
{
    public function __construct(
        public ?int $store_id = null,
    ) {}

    /** Attributes for new models that aren't in the file */
    public function defaults(array $data): array
    {
        return ['store_id' => $this->store_id, 'status' => 'draft'];
    }

    /** After the values are read, before validation */
    public function prepareRow(array $data, int $row): array
    {
        $data['sku'] = isset($data['sku']) ? strtoupper($data['sku']) : null;

        return $data;
    }

    protected function beforeSave(Model $model, array $data, int $row): void
    {
        if ($model->exists && $model->is_locked) {
            throw RowFailedException::withMessage(__('This product is locked.'), 'sku');
        }
    }

    protected function afterSave(Model $model, array $data, int $row): void
    {
        $model->tags()->sync($data['tag_ids'] ?? []);
    }

    public function existingQuery(): Builder
    {
        return Product::query()->where('store_id', $this->store_id);
    }
}

ProductsImporter::make(store_id: 5)->import($path);
```

Throw `RowFailedException` from any hook to fail the row with your message; the row is rolled back and the import carries on. `beforePersist(array $rows)` gets each chunk's valid rows first, to load what they need at once. Override `fill()` or `persist()` to change how models are saved.

Keep constructor arguments small and serializable (ids, strings): queued imports serialize the importer. The imports page makes importers with `new ProductsImporter`, or with `fromRequest(Request $request)` when you define it:

```php
public static function fromRequest(Request $request): static
{
    return new static(store_id: $request->user()->store_id);
}
```

# Other settings

| Method | Default |
| --- | --- |
| `label()` | The model's plural (`Products`), translated. Shown on the imports page. |
| `authorize(?Model $user)` | `$user->can('create', modelClass())` |
| `headingRow()` | `imports.heading_row` (1) |
| `sheet()` | `0`, the first sheet; an index or a sheet name |
| `chunkSize()` | `imports.chunk_size` (500) |
| `connectionName()` | The default connection, for the chunk transactions |

# Importing anything else

Extend `Importer` and save rows yourself:

```php
use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Enums\RowOutcomes;
use Javaabu\Imports\Importer;

class ReadingsImporter extends Importer
{
    public function columns(): array
    {
        return [
            Column::make('meter')->required(),
            Column::make('reading')->required()->number(),
            Column::make('read_on')->date()->default(fn () => today()->toDateString()),
        ];
    }

    public function persist(array $data, int $row): RowOutcomes
    {
        Meter::query()->where('number', $data['meter'])->firstOrFail()->readings()->create($data);

        return RowOutcomes::CREATED;
    }
}
```

Return `RowOutcomes::CREATED`, `UPDATED` or `SKIPPED`; override `previewOutcome()` to say which in previews and dry runs.
