<?php
/**
 * Custom Exception
 *
 * @author Arushad Ahmed (@dash8x)
 * @author_uri http://arushad.org
 */

namespace Javaabu\Imports\Exceptions;

use Illuminate\Http\JsonResponse;

class TooManyRowsException extends AppException
{
    /**
     * @var int
     */
    protected $count;

    /**
     * @var string
     */
    protected $file_name;

    /**
     * Constructor
     *
     * @param int $count
     * @param string $file_name
     * @param string $message
     * @param string $name
     */
    public function __construct(int $count, $file_name = '', $message = 'The import file contains too many rows to import in one go. The data would be imported in the background and you would be notified via email once the data is imported.', $name = 'TooManyRows')
    {
        parent::__construct(422, $name, $message);

        $this->count = $count;
        $this->file_name = $file_name;
    }

    /**
     * Get the count
     *
     * @return int
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * Get the filename
     *
     * @return int
     */
    public function getFileName()
    {
        return $this->file_name;
    }

    /**
     * Send json response
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response
     */
    protected function sendHttpResponse()
    {
        return back()->with([
            'row_count' => $this->getCount(),
            'file_name' => $this->getFileName(),
            'import_queued' => true,
        ]);
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
            'error' => $this->getName(),
            'row_count' => $this->getCount(),
            'file_name' => $this->getFileName(),
        ], $this->getStatusCode());
    }

}
