# Common mistakes with javaabu/imports

## Using v1 / maatwebsite APIs

```php
// Wrong (v1)
Excel::import(new ProductImporter, $request->file('import_file'));

class ProductImporter extends \Javaabu\Imports\Importers\Importer
{
    public function rowValidationRules(array $row): array { /* … */ }
    public function getExistingModel(array $row): ?Model { /* … */ }
    public function saveRow(array $row, ?Model $existing_model = null): Model { /* … */ }
}

// Right
ProductsImporter::make()->import($request->file('file'));

class ProductsImporter extends ModelImporter
{
    public function modelClass(): string { return Product::class; }
    public function columns(): array { return [Column::make('sku')->required(), /* … */]; }
    public function uniqueBy(): array { return ['sku']; }
}
```

## Querying per row

```php
// Wrong: a query for every row, twice
Column::make('brand_id')->rules('exists:brands,id'),

protected function beforeSave(Model $model, array $data, int $row): void
{
    $model->brand_id = Brand::where('name', $data['brand'])->value('id');
}

// Right: one query per chunk, and a clear error for unknown brands
Column::make('brand_id')->label(__('Brand'))->lookup(Brand::class, ['name', 'code']),
```

```php
// Wrong: a unique rule fails every row that should update
Column::make('sku')->rules('unique:products,sku'),

// Right
public function uniqueBy(): array
{
    return ['sku'];
}

ProductsImporter::make()->duplicates('update')->import($file);
```

## Parsing cells by hand

```php
// Wrong: misses "Yes", "TRUE", Excel dates and Dhivehi
Column::make('is_active')->transform(fn ($value) => $value === '1'),
Column::make('released_on')->transform(fn ($value) => Carbon::createFromFormat('d/m/Y', $value)),

// Right
Column::make('is_active')->boolean(),
Column::make('released_on')->date(),
```

## Stopping the import for one row

```php
// Wrong: one bad row fails the whole job and loses the report
protected function beforeSave(Model $model, array $data, int $row): void
{
    abort_if($model->is_locked, 422, 'Locked');
}

// Right: the row is rolled back, reported, and the import carries on
protected function beforeSave(Model $model, array $data, int $row): void
{
    if ($model->is_locked) {
        throw RowFailedException::withMessage(__('This product is locked.'), 'sku');
    }
}
```

## Importing big files in the request

```php
// Wrong: 50,000 rows in a web request
$result = ProductsImporter::make()->import($request->file('file'));

// Right
$import = ProductsImporter::make()->queue($request->file('file'), $request->user());

return redirect()->route('imports.show', $import);
```

## Unserializable importers

```php
// Wrong: a collection and a model are serialized into the queue payload
ProductsImporter::make(Store::with('products')->find($id))->queue($file, $user);

// Right: pass an id
ProductsImporter::make(store_id: $id)->queue($file, $user);
```

## Skipping the preview

When a person uploads a file, show `preview()` (or send them to the imports page) before running it: they see which columns map where and which rows will fail, before anything is saved.

## Untranslated labels

```php
// Wrong
Column::make('brand_id')->label('Brand'),   // works, but add the Dhivehi to lang/dv.json

// Right: __() runs on labels, so add "Brand": "ބްރޭންޑް" to lang/dv.json
```
