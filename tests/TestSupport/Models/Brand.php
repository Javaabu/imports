<?php

namespace Javaabu\Imports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $guarded = [];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
