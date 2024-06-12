<?php

namespace Javaabu\Imports\Http\Controllers;

use Illuminate\Http\Request;
use Javaabu\Imports\Traits\ImportsData;
use App\Imports\Http\Requests\ImportsRequest;
use Javaabu\Auth\User;
use Javaabu\Helpers\Http\Controllers\Controller;
use Javaabu\Imports\ImportsRepository;

class ImportsController extends Controller
{
    use ImportsData;

    public function __construct()
    {
//        $this->middleware('can:importAny,'.User::class);
    }

    /**
     * Show import form
     */
    public function index(Request $request)
    {
        $importables = ImportsRepository::getImportablesList($request->user());
        $view = config('imports.view');
        return view($view, compact('importables'));
    }

    /**
     * Get the redirector
     */
    public function getImportRedirect()
    {
        return redirect()->route('admin.imports.index');
    }

    /*
     * Import the data or send the import template
     */
    public function store(ImportsRequest $request)
    {
        return $this->importData($request);
    }
}
