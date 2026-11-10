<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Javaabu\Imports\Models\Import;

/**
 * Tells the user their import stopped with an error
 */
class ImportFailedNotification extends Notification
{
    public function __construct(
        public Import $import,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return config('imports.notifications.channels', ['mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('Your :name import failed', ['name' => $this->import->name]))
            ->line(__('Your :name import of :file could not be completed. Rows saved before the error stay saved. Please try again, or contact us if it keeps failing.', [
                'name' => $this->import->name,
                'file' => $this->import->filename,
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'import' => $this->import->uuid,
            'name' => $this->import->name,
            'status' => $this->import->status->value,
            'message' => __('Your :name import failed', ['name' => $this->import->name]),
        ];
    }
}
