<?php

namespace Javaabu\Imports\Exceptions;

use RuntimeException;

/**
 * Thrown inside a running import when it was cancelled, to stop at the next chunk
 */
class ImportCancelledException extends RuntimeException {}
