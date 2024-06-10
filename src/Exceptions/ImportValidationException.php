<?php
/**
 * Custom Exception
 *
 * @author Arushad Ahmed (@dash8x)
 * @author_uri http://arushad.org
 */

namespace Javaabu\Imports\Exceptions;

use App\Exceptions\AppException;
use Illuminate\Http\JsonResponse;

class ImportValidationException extends AppException
{
    /**
     * @var array
     */
    protected $errors;

    /**
     * Constructor
     *
     * @param array $errors
     * @param string $message
     * @param string $name
     */
    public function __construct(array $errors, $message = 'Import data is invalid', $name = 'ImportValidationErrors')
    {
        parent::__construct(422, $name, $message);

        $this->errors = $errors;
    }

    /**
     * Get the errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Send json response
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    protected function sendHttpResponse()
    {
        return back()->withErrors($this->getErrors(), 'import_errors');
    }

    /**
     * Send json response
     *
     * @return JsonResponse
     */
    protected function sendJsonResponse()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->getErrors()
        ], $this->getStatusCode());
    }

}
