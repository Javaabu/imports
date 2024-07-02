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

    public function phoneVerificationRedirectUrl(): string
    {
        return '';
    }

    public function phoneVerificationUrl(): string
    {
        return '';
    }

    public function findMobileGrantUser($oauth_user, $provider): ?HasMobileNumber
    {
        $number = $http_response_header->getNumber();
        $user = User::whereHas('phone', function ($query) use ($number) {
            $query->where('number', $number);
        })->first();

        return $user;
    }

    public function redirectToMobileVerificationUrl(): RedirectResponse
    {
        return to_route('mobile-verifications.login.create');
    }
}
