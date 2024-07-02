<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ImportValidationErrorsNotification extends Notification
{
    public array $errors;
    public string $file_name;
    public string $model_type;

    public function __construct(array $errors, string $file_name, string $model_type)
    {
        $this->errors = $errors;
        $this->file_name = $file_name;
        $this->model_type = $model_type;
    }

    public function via(mixed $notifiable): array|string
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
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
