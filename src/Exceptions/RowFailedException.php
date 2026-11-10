<?php

namespace Javaabu\Imports\Exceptions;

use RuntimeException;

/**
 * Throw from an importer's hooks to fail the current row with a message the user sees.
 * The row is rolled back and added to the failure report; the import carries on.
 */
class RowFailedException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors  attribute => messages
     */
    public function __construct(string $message, protected array $errors = [])
    {
        parent::__construct($message);
    }

    public static function withMessage(string $message, string $attribute = 'row'): static
    {
        return new static($message, [$attribute => [$message]]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors ?: ['row' => [$this->getMessage()]];
    }
}
