<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ImportFailedNotification extends Notification
{
    /**
     * The validation errors
     *
     * @var string
     */
    public $message;

    /**
     * The file name
     *
     * @var string
     */
    public $file_name;

    /**
     * The model type
     *
     * @var string
     */
    public $model_type;

    /**
     * Create a notification instance.
     */
    public function __construct(?string $message, string $file_name, string $model_type)
    {
        $this->message = $message;
        $this->file_name = $file_name;
        $this->model_type = $model_type;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
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
