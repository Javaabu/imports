<?php

namespace Javaabu\Imports\Traits;

use App\Imports\Http\Requests\ImportsRequest;
use Javaabu\Imports\ImportsRepository;
use Maatwebsite\Excel\Facades\Excel;

trait ImportsData
{
    /**
     * Get the redirector
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function getImportRedirect()
    {
        return redirect()->back();
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
                'import_file' => 'The imported file is empty'
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
