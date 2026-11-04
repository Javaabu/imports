<?php

namespace Javaabu\Imports\Tests\TestSupport\Importers;

use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\ModelImporter;
use Javaabu\Imports\Tests\TestSupport\Enums\ProductStatuses;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;
use Javaabu\Imports\Tests\TestSupport\Models\Product;

class ProductsImporter extends ModelImporter
{
    public function modelClass(): string
    {
        return Product::class;
    }

    public function columns(): array
    {
        return [
            Column::make('sku')->label('SKU')->required()->rules('string|max:50')->aliases(['code', 'item code'])->example('TV-001'),
            Column::make('name')->required()->string()->rules('max:255')->example('ޓީވީ'),
            Column::make('price')->number()->rules('min:0')->default(0)->example(1250.5),
            Column::make('stock')->integer()->example(10),
            Column::make('status')->enum(ProductStatuses::class)->default(ProductStatuses::DRAFT->value),
            Column::make('is_active')->label('Active')->boolean(),
            Column::make('released_on')->date(),
            Column::make('brand_id')->label('Brand')->lookup(Brand::class, ['name', 'code']),
        ];
    }

    public function uniqueBy(): array
    {
        return ['sku'];
    }
}
