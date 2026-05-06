<?php

namespace App\Jobs;

use App\Helpers\CryptHelper;
use App\Helpers\RocketChatHelper;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class CreateAssociateChatRoom implements ShouldQueue
{
    use Queueable;

    protected string $userIdentity;
    protected array $participantIdentities;


    /**
     * Create a new job instance.
     */
    public function __construct(string $userIdentity, array $participantIdentities)
    {
        $this->userIdentity = $userIdentity;
        $this->participantIdentities = $participantIdentities;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::where('identity', $this->userIdentity)->firstOrFail();

        $participantIdentities = $this->participantIdentities;

        if ($user->enabled) {
            $auth = RocketChatHelper::login($user->identity, CryptHelper::decrypt($user->chat_password));

            $authToken = $auth['authToken'] ?? null;
            $userId = $auth['userId'] ?? null;
    
            if (!$authToken || !$userId) {
                throw new \Exception('Rocket.Chat authentication failed');
            }
    
            foreach($participantIdentities as $identity) {
                Http::withHeaders([
                    'X-Auth-Token' => $authToken,
                    'X-User-Id' => $userId,
                ])->asJson()->post(config('rocketchat.create_room_url'), [
                    'username' => $identity,
                ]);
            }
    
            RocketChatHelper::logout($userId, $authToken);
        }
    }
}
