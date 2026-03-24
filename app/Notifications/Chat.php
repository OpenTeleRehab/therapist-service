<?php

namespace App\Notifications;

use App\Broadcasting\FcmChannel;
use App\Helpers\TranslationHelper;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;

class Chat extends Notification
{
    use Queueable;

    private ?string $rid;
    private string $title;
    private string $body;
    private bool $translatable;

    /**
     * Create a new notification instance.
     */
    public function __construct(?string $id, ?string $rid, string $title, string $body, bool $translatable)
    {
        $this->id = $id;
        $this->rid = $rid;
        $this->title = $title;
        $this->body = $body;
        $this->translatable = $translatable;
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
        if (str_starts_with($this->body, 'jitsi_call') && (str_ends_with($this->body, '_started') || str_ends_with($this->body, '_missed') || str_ends_with($this->body, '_accepted'))) {
            $message = CloudMessage::new()
                ->withData([
                    '_id' => $this->id,
                    'rid' => $this->rid,
                    'title' => $this->title,
                    'body' => $this->body,
                    'channelId' => 'fcm_call_channel',
                ])
                ->withApnsConfig(
                    ApnsConfig::fromArray([
                        'headers' => [
                            'apns-expiration' => (string) (time() + 60), // iOS expire time (unix timestamp)
                            'apns-push-type' => 'background',
                            'apns-priority' => '5',
                            'apns-topic' => '',
                        ],
                        'payload' => [
                            'aps' => [
                                'badge' => 1,
                                'content-available' => 1,
                            ],
                        ],
                    ]),
                )
                ->withAndroidConfig(
                    AndroidConfig::fromArray([
                        'ttl' => '60s', // expires in 60 seconds
                        'priority' => 'high',
                    ]),
                );
        } else {
            // Get translations.
            $translations = TranslationHelper::getTranslations($notifiable->language_id, 'patient_app');

            $message = CloudMessage::new()
                ->withNotification([
                    'title' => str_replace('${name}', $this->title, $translations['chat_message.notification.title']),
                    'body' => $this->translatable ? $translations[$this->body] : $this->body,
                ])
                ->withApnsConfig(
                    ApnsConfig::fromArray([
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'aps' => [
                                'badge' => 1,
                            ],
                        ],
                    ]),
                )
                ->withAndroidConfig(
                    AndroidConfig::fromArray([
                        'priority' => 'high',
                    ]),
                );
        }

        // Get device tokens.
        $deviceTokens = $notifiable->devices->pluck('fcm_token')->toArray();

        if (count($deviceTokens) === 0) {
            Log::info('No device FCM tokens found for user', ['user_id' => $notifiable->id]);
            return;
        }

        // Send to multiple tokens.
        $report = $messaging->sendMulticast($message, $deviceTokens);

        if ($report->hasFailures()) {
            foreach ($report->failures()->getItems() as $failure) {
                Log::error($failure->error()->getMessage().PHP_EOL);
            }
        }
    }
}
