---
title: Setting Up
sidebar_position: 1.5
---

## Create Importer
Create a new class that extends `Javaabu\Imports\Importers\Importer` class. This class will be responsible for handling the import process.
The package provides a simple command to create a new importer class. Run the following command to create a new importer class.

```bash
php artisan imports:make carriers
```

This command will create a new importer class in the `App\Imports\Importers` namespace. The class will be named `CarriersImporter`.
The generated class will look like this:

```php
<?php

namespace App\Imports\Importers;

use App\Models\User;
use App\Models\Carrier;
use Illuminate\Database\Eloquent\Model;
use Javaabu\Imports\Importers\Importer;

class CarrierImporter extends Importer
{
    public function dummyData(): array
    {
        // TODO: Implement dummyData() method.
    }

    public function headings(): array
    {
        // TODO: Implement headings() method.
    }

    public function rowValidationRules(array $row): array
    {
        // TODO: Implement rowValidationRules() method.
    }

    public function getExistingModel(array $row): ?Model
    {
        // TODO: Implement getExistingModel() method.
    }

    public function saveRow(array $row, Model $existing_model = null): Model
    {
        // TODO: Implement saveRow() method.
    }
}
```

After implementing the required methods, the importer class will be ready to use. 

## Register Importer
To use the importer class, you need to register it via Service Provider. The package provides a simple command to register the importer class.

In any of your service providers boot method, you can register the importer class using the `Imports::registerImports` method.

```php
    Imports::registerImports([
        'carrier' => CarrierImporter::class
    ]);
```
