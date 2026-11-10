<?php

namespace Javaabu\Imports\Enums;

enum ImportStatuses: string
{
    /** Uploaded, waiting for its columns to be mapped */
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::PENDING => __('Pending'),
            self::PROCESSING => __('Processing'),
            self::COMPLETED => __('Completed'),
            self::FAILED => __('Failed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    /**
     * The javaabu/forms status colour
     */
    public function color(): string
    {
        return match ($this) {
            self::DRAFT, self::PENDING => 'secondary',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'warning',
        };
    }

    /**
     * Whether the import has stopped, successfully or not
     */
    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Whether the import is queued or running and can still be cancelled
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING], true);
    }
}
