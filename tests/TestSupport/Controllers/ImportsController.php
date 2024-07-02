<?php

namespace Javaabu\Imports\Tests\TestSupport\Controllers;

use Javaabu\Helpers\Http\Controllers\Controller;
use Javaabu\Imports\Traits\ImportsData;

class ImportsController extends Controller
{
    use ImportsData;

    public function getStoreRouteUrl(): string
    {
        return route('imports.store');
    }
}
