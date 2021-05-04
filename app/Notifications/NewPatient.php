<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPatient extends Notification
{
    use Queueable;

    private $patientFirstName;
    private $patientLastName;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($patientFirstName, $patientLastName)
    {
        $this->patientFirstName = $patientFirstName;
        $this->patientLastName = $patientLastName;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * @param $notifiable
     *
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'patient_first_name' => $this->patientFirstName,
            'patient_last_name' => $this->patientLastName,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
