<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Support\Str;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ImportFailedNotification extends Notification
{
    public string $message; // The validation errors
    public string $file_name;
    public string $model_type;

    public function __construct(?string $message, string $file_name, string $model_type)
    {
        $this->message = $message;
        $this->file_name = $file_name;
        $this->model_type = $model_type;
    }

    /**
     * Get the notification's channels.
     */
    public function via(mixed $notifiable): array|string
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $model_type = Str::plural(slug_to_title($this->model_type));

        return (new MailMessage())
            ->subject("$model_type Import Failed!")
            ->greeting("Hi {$notifiable->name},")
            ->error()
            ->line("The **$model_type Import** for **'{$this->file_name}'** failed with the following error message.")
            ->line('')
            ->line("`{$this->message}`");
    }
}
