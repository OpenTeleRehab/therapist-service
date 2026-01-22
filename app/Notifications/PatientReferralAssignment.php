<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientReferralAssignment extends Notification
{
    // use Queueable;

    private string $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $status)
    {
        $this->subject = 'OpenTeleRehab – Patient Referral Notification';
        $this->status = $status;
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
            ->lineIf($this->status === 'invited', 'Please be informed that a rehab service admin, [Rehab Service Admin Name], has assigned your patient referral request to a therapist. Kindly log in to the portal for more information.')
            ->lineIf($this->status === 'accepted', 'Please be informed that a therapist, [Therapist Name], has accepted your patient referral request. Kindly log in to the portal for more information.')
            ->lineIf($this->status === 'declined', 'Please be informed that a therapist, [Therapist Name], has declined your patient referral request. Kindly log in to the portal for more information.');
    }
}
