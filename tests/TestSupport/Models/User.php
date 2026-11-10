<?php

namespace Javaabu\Imports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Javaabu\Imports\Tests\TestSupport\Factories\UserFactory;
use Javaabu\Permissions\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected string $guard_name = 'web';

    protected static function newFactory(): UserFactory
    {
        return new UserFactory;
    }
}
