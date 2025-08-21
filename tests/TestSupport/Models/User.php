<?php

namespace Javaabu\Imports\Tests\TestSupport\Models;

use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Javaabu\Imports\Tests\TestSupport\Factories\UserFactory;

class User extends Authenticatable
{
    use HasFactory;
    use SoftDeletes;

    protected static function newFactory()
    {
        return new UserFactory();
    }
}
