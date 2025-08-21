<?php

namespace Javaabu\Imports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Javaabu\Imports\Tests\TestSupport\Factories\CategoryTypeFactory;

class CategoryType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'slug',
        'name',
        'has_icon',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'has_icon' => 'boolean',
    ];

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return new CategoryTypeFactory();
    }

    /**
     * Get the categories for this category type.
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
