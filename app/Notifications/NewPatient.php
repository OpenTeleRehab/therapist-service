<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPatient extends Notification
{
    use Queueable;

    /**
     * @var string
     */
    private $patientFirstName;

    /**
     * @var string
     */
    private $patientLastName;

    /**
     * Create a new notification instance.
     *
     * @param string $patientFirstName
     * @param string $patientLastName
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
     * @param mixed $notifiable
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
}
