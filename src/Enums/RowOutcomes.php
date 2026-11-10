<?php

namespace Javaabu\Imports\Enums;

/**
 * What an importer did (or, in a preview or dry run, would do) with a valid row
 */
enum RowOutcomes: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case SKIPPED = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => __('New'),
            self::UPDATED => __('Update'),
            self::SKIPPED => __('Skip'),
        };
    }
}
