<?php

namespace Javaabu\Imports\Traits;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Javaabu\Imports\ImportsRepository;
use Javaabu\Imports\Http\Requests\ImportsRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait ImportsData
{
    public function index(Request $request): View
    {
        return view($this->getIndexView(), [
            'importables' => ImportsRepository::getImportablesList($request->user()),
            'store_route_url' => $this->getStoreRouteUrl(),
            'layouts_view' => $this->getLayoutsView(),
        ]);
    }

    public function getIndexView(): string
    {
        return 'imports::material-admin.imports.index';
    }

    public function getStoreRouteUrl(): string
    {
        return route('admin.imports.store');
    }

    public function getLayoutsView(): string
    {
        return 'layouts.admin';
    }

    public function store(ImportsRequest $request): Redirector|RedirectResponse|BinaryFileResponse
    {
        return $this->importData($request);
    }

    /**
     * Get the redirector
     */
    public function getImportRedirect(): Redirector|RedirectResponse
    {
        return redirect()->to(action([self::class, 'index']));
    }

    public function importData(ImportsRequest $request, $meta = null): Response|BinaryFileResponse|Redirector|RedirectResponse
    {
        $model = $request->input('model');

        $overwrite_duplicates = ! empty($request->input('overwrite_duplicates', false));

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
            'num_imported' => $importer->count(),
            'num_duplicates' => $importer->numDuplicates(),
            'duplicates' => $importer->duplicates(),
            'overwrite' => $overwrite_duplicates,
        ];

        return $redirect->with('import_result', $import_result);
    }
}
