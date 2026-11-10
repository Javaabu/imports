<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Javaabu\Imports\Models\Import;

/**
 * Tells the user their import finished, with its counts and a link to the failed rows
 */
class ImportCompletedNotification extends Notification
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
        $import = $this->import;

        $message = (new MailMessage)
            ->subject($this->subject())
            ->line(__('Your :name import of :file has finished.', ['name' => $import->name, 'file' => $import->filename]))
            ->line(__('New: :count', ['count' => number_format($import->created_rows)]))
            ->line(__('Updated: :count', ['count' => number_format($import->updated_rows)]))
            ->line(__('Skipped: :count', ['count' => number_format($import->skipped_rows)]))
            ->line(__('Failed: :count', ['count' => number_format($import->failed_rows)]));

        if ($import->dry_run) {
            $message->line(__('This was a dry run: nothing was saved.'));
        }

        if ($url = $import->temporaryReportUrl()) {
            $message->action(__('Download failed rows'), $url);
        }

        return $message;
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
            'created' => $this->import->created_rows,
            'updated' => $this->import->updated_rows,
            'skipped' => $this->import->skipped_rows,
            'failed' => $this->import->failed_rows,
            'report_url' => $this->import->temporaryReportUrl(),
            'message' => $this->subject(),
        ];
    }

    protected function subject(): string
    {
        return $this->import->failed_rows > 0
            ? __('Your :name import finished with :count failed rows', ['name' => $this->import->name, 'count' => number_format($this->import->failed_rows)])
            : __('Your :name import is complete', ['name' => $this->import->name]);
    }
}
