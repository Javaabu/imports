<?php

namespace Javaabu\Imports\Enums;

/**
 * What happens to a row that matches an existing record (by the importer's uniqueBy())
 */
enum Duplicates: string
{
    /** Leave the existing record alone and count the row as skipped */
    case SKIP = 'skip';

    /** Update the existing record with the row */
    case UPDATE = 'update';

    /** Count the row as failed */
    case FAIL = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::SKIP => __('Skip existing records'),
            self::UPDATE => __('Update existing records'),
            self::FAIL => __('Fail rows that already exist'),
        };
    }

    public static function resolve(self|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom((string) $value)
            ?? self::tryFrom((string) config('imports.duplicates', self::SKIP->value))
            ?? self::SKIP;
    }
}
