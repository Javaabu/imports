<?php

namespace Javaabu\Imports\Tests\TestSupport\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Javaabu\Imports\Http\Controllers\ImportsController;

class CustomImportsController extends ImportsController
{
    public function index(Request $request): View|Responsable|JsonResponse
    {
        return response()->json(['custom' => true]);
    }
}
