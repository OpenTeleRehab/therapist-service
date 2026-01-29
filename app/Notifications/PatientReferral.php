<?php

namespace App\Notifications;

use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class PatientReferral extends Notification
{
    // use Queueable;

    private string $subject;
    private string $content;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user, string $prefix)
    {
        $endpoint = env('ADMIN_SERVICE_URL') . '/email-templates/'. $prefix .'/get-by-prefix';
        $accessToken = Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE);

        $response = Http::withToken($accessToken)->get($endpoint, [
            'lang' => $user->language_id,
        ]);

        if ($response->successful()) {
            $response = $response->json();
            $data = $response['data'];

            $this->subject = config('mail.from.name') . ' - ' . $data['title'];
            $this->content = $data['content'];
            $this->content = str_replace('#user_name#', $user->first_name, $this->content);
            $this->content = str_replace('#rehab_service_admin_name#', '[Rehab Service Admin Name]', $this->content);
        }
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
            ->view('emails.patient-referral', [
                'content' => $this->content,
            ]);
    }
}
