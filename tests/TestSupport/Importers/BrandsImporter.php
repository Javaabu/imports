<?php

namespace Javaabu\Imports\Tests\TestSupport\Importers;

use Javaabu\Imports\Columns\Column;
use Javaabu\Imports\Enums\RowOutcomes;
use Javaabu\Imports\Exceptions\RowFailedException;
use Javaabu\Imports\Importer;
use Javaabu\Imports\Tests\TestSupport\Models\Brand;

/**
 * A plain importer: persist() saves the row itself
 */
class BrandsImporter extends Importer
{
    public function __construct(
        public string $prefix = '',
    ) {}

    public function columns(): array
    {
        return [
            Column::make('name')->required()->transform(fn (string $value) => $this->prefix . $value),
            Column::make('code')->aliases('brand code'),
        ];
    }

    public function persist(array $data, int $row): RowOutcomes
    {
        if ($data['name'] === $this->prefix . 'Forbidden') {
            throw RowFailedException::withMessage('This brand is not allowed.', 'name');
        }

        Brand::query()->create($data);

        return RowOutcomes::CREATED;
    }
}
