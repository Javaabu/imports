<?php
/**
 * Custom Exception
 *
 * @author Arushad Ahmed (@dash8x)
 * @author_uri http://arushad.org
 */

namespace Javaabu\Imports\Exceptions;

use App\Imports\Exports\ErrorsExport;
use Maatwebsite\Excel\Concerns\Exportable;

class DownloadableImportValidationException extends ImportValidationException
{
    /**
     * @var Exportable
     */
    protected $exportable;

    /**
     * @var string
     */
    protected $export_file_name;

    /**
     * Constructor
     *
     * @param ErrorsExport $exportable
     * @param $export_file_name
     * @param array $errors
     */
    public function __construct(ErrorsExport $exportable, $export_file_name, array $errors)
    {
        parent::__construct($errors);

        $this->export_file_name = $export_file_name;
        $this->exportable = $exportable;

    }

    /**
     * Get the exportable file name
     *
     * @return string
     */
    public function getExportFileName()
    {
        return $this->export_file_name;
    }

    /**
     * Get the exportable
     *
     * @return Exportable
     */
    public function getExportable()
    {
        return $this->exportable;
    }

    /**
     * Send json response
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    protected function sendHttpResponse()
    {
        return $this->getExportable()->download($this->getExportFileName());
    }

}
