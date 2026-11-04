<?php

namespace Javaabu\Imports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Javaabu\Imports\Tests\TestSupport\Enums\ProductStatuses;

class Product extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['api_token'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'status' => ProductStatuses::class,
            'is_active' => 'boolean',
            'released_on' => 'date',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
