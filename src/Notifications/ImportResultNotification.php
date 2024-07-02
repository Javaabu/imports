<?php

namespace Javaabu\Imports\Notifications;

use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ImportResultNotification extends Notification
{
    /**
     * The result
     *
     * @var array
     */
    public $result;

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
    public function __construct(array $result, string $file_name, string $model_type)
    {
        $this->result = $result;
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
        $result = $this->result;

        $num_imported = $result['num_imported'];
        $num_duplicates = $result['num_duplicates'];
        $overwrite = $result['overwrite'];
        $duplicates = $result['duplicates'];

        $model_type = Str::plural(slug_to_title($this->model_type));

        $message = (new MailMessage())
            ->subject("$model_type Import Completed")
            ->greeting("Hi {$notifiable->name},")
            ->success()
            ->line("The **$model_type Import** for the **'{$this->file_name}'** file was successfully completed. Here's the result of the import.")
            ->line('')
            ->line("**Total Records Imported:** $num_imported")
            ->line('**'.($overwrite ? 'Total Duplicate Records Overwritten' : 'Total Duplicate Records Skipped').':** '.$num_duplicates)
            ->line('');

        if ($duplicates) {
            $message->line('**Duplicate Rows**');
            $html = '<ul>';

            foreach ($duplicates as $row) {
                $html .= '<li>Row '.e($row).'</li>';
            }

            $html .= '</ul><br>';

            $message->line(new HtmlString($html));
        }

        return $message;
    }
}
