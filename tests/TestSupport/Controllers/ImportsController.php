<?php

namespace Javaabu\Imports\Tests\TestSupport\Controllers;

use Javaabu\Imports\Traits\ImportsData;
use Javaabu\Helpers\Http\Controllers\Controller;

class ImportsController extends Controller
{
    use ImportsData;

    public function getStoreRouteUrl(): string
    {
        return route('imports.store');
    }
}
