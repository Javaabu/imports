<?php
/**
 * Custom Exception
 *
 * @author Arushad Ahmed (@dash8x)
 *
 * @author_uri http://arushad.org
 */

namespace Javaabu\Imports\Exceptions;

use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Javaabu\Imports\Exports\ErrorsExport;

class DownloadableImportValidationException extends ImportValidationException
{
    protected ErrorsExport $exportable;

    protected string $export_file_name;

    /**
     * Constructor
     */
    public function __construct(ErrorsExport $exportable, $export_file_name, array $errors)
    {
        parent::__construct($errors);

        $this->export_file_name = $export_file_name;
        $this->exportable = $exportable;

    }

    /**
     * Get the exportable file name
     */
    public function getExportFileName(): string
    {
        return $this->export_file_name;
    }

    /**
     * Get the exportable
     */
    public function getExportable(): ErrorsExport
    {
        return $this->exportable;
    }

    /**
     * Send json response
     */
    protected function sendHttpResponse(): Response|RedirectResponse|BinaryFileResponse
    {
        return $this->getExportable()->download($this->getExportFileName());
    }
}
