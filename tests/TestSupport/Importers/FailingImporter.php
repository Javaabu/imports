<?php

namespace Javaabu\Imports\Tests\TestSupport\Importers;

use RuntimeException;

class FailingImporter extends ProductsImporter
{
    public function beforePersist(array $rows): void
    {
        throw new RuntimeException('The warehouse is closed.');
    }
}
