<?php

namespace App\Notifications;

use App\Broadcasting\FcmChannel;
use App\Helpers\TranslationHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

class Transfer extends Notification
{
    // use Queueable;

    public $title;
    public $body;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $body)
    {
        $this->title = $title;
        $this->body = $body;
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

        // Create the message.
        $message = CloudMessage::new()
            ->withNotification([
                'title' => $this->title,
                'body' => $this->body,
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
