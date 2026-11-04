<?php

namespace Javaabu\Imports\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * The column mapping can't run: required columns aren't mapped, or a column is mapped twice
 */
class InvalidMappingException extends ValidationException
{
    /**
     * @param  array<string, array<int, string>|string>  $errors
     */
    public static function fromErrors(array $errors): static
    {
        return static::withMessages($errors);
    }
}
