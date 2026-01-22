<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientCounterReferral extends Notification
{
    // use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        $this->subject = 'OpenTeleRehab – Counter-refer Notification';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->greeting("Dear $notifiable->first_name,")
            ->line('Please be informed that a therapist, [Therapist Name], has counter-referred a patient to your PHC service. Kindly log in to the portal for more information.');
    }
}
