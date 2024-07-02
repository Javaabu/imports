<?php

namespace Javaabu\Imports\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;
use Javaabu\Imports\Contracts\IsImporter;
use Javaabu\Imports\Http\Requests\ImportsRequest;
use Javaabu\Imports\ImportsRepository;
use Maatwebsite\Excel\Facades\Excel;

trait ImportsData
{

    public function index(Request $request): View
    {
        return view($this->getIndexView(), [
            'importables'     => ImportsRepository::getImportablesList($request->user()),
            'store_route_url' => $this->getStoreRouteUrl(),
            'layouts_view'    => $this->getLayoutsView(),
        ]);
    }

    public function getIndexView(): string
    {
        return 'imports::material-admin.imports.index';
    }

    public function getStoreRouteUrl(): string
    {
        return route('imports.store');
    }

    public function getLayoutsView(): string
    {
        return 'layouts.admin';
    }

    public function store(ImportsRequest $request): Redirector|RedirectResponse
    {
        return $this->importData($request);
    }

    /**
     * Get the redirector
     *
     * @return Redirector|RedirectResponse
     */
    public function getImportRedirect(): Redirector|RedirectResponse
    {
        return redirect()->to(action(self::class, 'index'));
    }

    /*
     * Import
     *
     * @return Response
     */
    public function importData(ImportsRequest $request, $meta = null)
    {
        $model = $request->input('model');

        $overwrite_duplicates = ! empty($request->input('overwrite_duplicates', false));

        /* @var IsImporter $importer */
        $importer = ImportsRepository::getImporter(
            $model,
            $overwrite_duplicates,
            $request->input('error_handler', 'display'),
            $request->user(),
            $meta
        );

        // Download Template File
        if ($request->input('action') == 'download_template') {
            return $importer->downloadImportTemplate();
        }

        $redirect = $this->getImportRedirect();

        try {
            $importer->setFileName(
                $request->file('import_file')->getClientOriginalName()
            );

            Excel::import($importer, $request->file('import_file'));
        } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
            return $redirect->withErrors([
                'import_file' => 'The imported file is empty',
            ]);
        }

        $this->flashSuccessMessage();

        $import_result = [
            'num_imported'   => $importer->count(),
            'num_duplicates' => $importer->numDuplicates(),
            'duplicates'     => $importer->duplicates(),
            'overwrite'      => $overwrite_duplicates,
        ];

        return $redirect->with('import_result', $import_result);
    }
}
