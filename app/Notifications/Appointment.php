<?php

namespace App\Notifications;

use App\Broadcasting\FcmChannel;
use App\Helpers\TranslationHelper;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class Appointment extends Notification
{
    use Queueable;

    private \App\Models\Appointment $appointment;
    private string $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(\App\Models\Appointment $appointment, string $status)
    {
        $this->appointment = $appointment;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    /**
     * Push notification to specific customer.
     *
     * @param Messaging $messaging
     * @throws \Kreait\Firebase\Exception\FirebaseException
     * @throws \Kreait\Firebase\Exception\MessagingException
     *
     * @return void
     */
    public function toFcm(object $notifiable)
    {
        // Initialize firebase messaging.
        $messaging = app(Messaging::class);

        $translations = TranslationHelper::getTranslations($notifiable->language_id);

        switch ($this->status) {
            case \App\Models\Appointment::STATUS_ACCEPTED:
                $name = $this->appointment->recipient->first_name . ' ' . $this->appointment->recipient->last_name;
                $title = $translations['appointment.updated_appointment_with'] . ' ' . $name . ' ' . $translations['appointment.invitation.accepted'];
                break;
            case \App\Models\Appointment::STATUS_REJECTED:
                $name = $this->appointment->recipient->first_name . ' ' . $this->appointment->recipient->last_name;
                $title = $translations['appointment.updated_appointment_with'] . ' ' . $name . ' ' . $translations['appointment.invitation.rejected'];
                break;
            case \App\Models\Appointment::STATUS_CANCELLED:
                $name = $this->appointment->requester->first_name . ' ' . $this->appointment->requester->last_name;
                $title = $translations['appointment.deleted_appointment_with'] . ' ' . $name;
                break;
            case \App\Models\Appointment::STATUS_UPDATED:
                $name = $this->appointment->requester->first_name . ' ' . $this->appointment->requester->last_name;
                $title = $translations['appointment.updated_appointment_with'] . ' ' . $name;
                break;
            default:
                $name = $this->appointment->requester->first_name . ' ' . $this->appointment->requester->last_name;
                $title = $translations['appointment.invitation_appointment_with'] . ' ' . $name;
        }

        // Create the message.
        $message = CloudMessage::new()
            ->withData([
                'title' => $title,
                'event_type' => 'appointment',
                'start_date' => $this->appointment->start_date,
                'end_date' => $this->appointment->end_date,
            ])
            ->withDefaultSounds();

        $deviceTokens = $notifiable->devices->pluck('fcm_token')->toArray();

        // Send to multiple tokens.
        $report = $messaging->sendMulticast($message, $deviceTokens);

        if ($report->hasFailures()) {
            foreach ($report->failures()->getItems() as $failure) {
                Log::error($failure->error()->getMessage().PHP_EOL);
            }
        }
    }
}
