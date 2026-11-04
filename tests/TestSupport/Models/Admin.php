<?php

namespace Javaabu\Imports\Tests\TestSupport\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Javaabu\Imports\Tests\TestSupport\Factories\AdminFactory;
use Javaabu\Permissions\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $guarded = [];

    protected $hidden = ['password'];

    protected string $guard_name = 'web_admin';

    protected static function newFactory(): AdminFactory
    {
        return new AdminFactory;
    }
}
