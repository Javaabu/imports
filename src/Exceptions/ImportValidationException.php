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
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportValidationException extends AppException
{
    protected ?array $errors;

    public function __construct(array $errors, ?string $message = 'Import data is invalid', ?string $name = 'ImportValidationErrors')
    {
        parent::__construct(422, $name, $message);

        $this->errors = $errors;
    }

    /**
     * Get the errors
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * Send json response
     */
    protected function sendHttpResponse(): Response|RedirectResponse|BinaryFileResponse
    {
        return back()->withErrors($this->getErrors(), 'import_errors');
    }

    /**
     * Send json response
     */
    protected function sendJsonResponse(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->getErrors(),
        ], $this->getStatusCode());
    }
}
