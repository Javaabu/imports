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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Javaabu\Helpers\Exceptions\AppException;

class TooManyRowsException extends AppException
{
    protected int $count;

    protected ?string $file_name;

    public function __construct(
        int $count,
        ?string $file_name = '',
        ?string $message = 'The import file contains too many rows to import in one go. The data would be imported in the background and you would be notified via email once the data is imported.',
        ?string $name = 'TooManyRows'
    ) {
        parent::__construct(422, $name, $message);

        $this->count = $count;
        $this->file_name = $file_name;
    }

    /**
     * Get the count
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the filename
     */
    public function getFileName(): ?string
    {
        return $this->file_name;
    }

    /**
     * Send json response
     */
    protected function sendHttpResponse(): Response|RedirectResponse
    {
        return back()->with([
            'row_count' => $this->getCount(),
            'file_name' => $this->getFileName(),
            'import_queued' => true,
        ]);
    }

    /**
     * Send json response
     */
    protected function sendJsonResponse(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => $this->getName(),
            'row_count' => $this->getCount(),
            'file_name' => $this->getFileName(),
        ], $this->getStatusCode());
    }
}
