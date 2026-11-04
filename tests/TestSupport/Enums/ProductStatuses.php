<?php

namespace Javaabu\Imports\Tests\TestSupport\Enums;

enum ProductStatuses: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PUBLISHED => __('Published'),
            self::ARCHIVED => __('Archived'),
        };
    }
}
