<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ImportValidationErrorsNotification extends Notification
{
    /**
     * The validation errors
     *
     * @var array
     */
    public $errors;

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
    public function __construct(array $errors, string $file_name, string $model_type)
    {
        $this->errors = $errors;
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

        $message = (new MailMessage())
            ->subject("$model_type Import Validation Errors")
            ->greeting("Hi {$notifiable->name},")
            ->error()
            ->line("The **$model_type Import** for the **'{$this->file_name}'** file was aborted due to the following errors.")
            ->line('')
            ->line('');

        foreach ($this->errors as $row => $row_errors) {
            $html = '<br>**'.trans_choice("There is an error in row $row|There are errors in row $row", count($row_errors)).'**';

            $html .= '<ul>';

            foreach ($row_errors as $error_message) {
                $html .= '<li>'.e($error_message).'</li>';
            }

            $html .= '</ul>';

            $message->line(new HtmlString($html));
        }

        return $message;
    }
}
