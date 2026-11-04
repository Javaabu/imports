<?php

namespace Javaabu\Imports\Tests\TestSupport\Importers;

use Javaabu\Imports\ModelImporter;
use Javaabu\Imports\Tests\TestSupport\Models\Product;

/**
 * Columns, types, lookups and the unique key all worked out from the products table
 */
class DefaultProductsImporter extends ModelImporter
{
    public function modelClass(): string
    {
        return Product::class;
    }
}
