<?php

namespace App\Console\Commands;

use App\Helpers\RocketChatHelper;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class AlterTherapistIdentity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:alter-therapist-identity';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change therapist identity format';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $response = Http::get(env('ADMIN_SERVICE_URL') . '/get-org-by-name');

        if ($response->successful()) {
            $organization = $response->json();
            $orgIdentity = str_pad($organization['id'], 4, '0', STR_PAD_LEFT);
            $users = User::all();

            foreach ($users as $user) {
                $countryIdentity = str_pad($user->country_id, 4, '0', STR_PAD_LEFT);
                $clinicIdentity = str_pad($user->clinic_id, 4, '0', STR_PAD_LEFT);
                $identity = 'T' . $orgIdentity . $countryIdentity . $clinicIdentity .
                    str_pad($user->id, 5, '0', STR_PAD_LEFT);

                // Update user chat username.
                $data = [
                    'username' => $identity,
                    'password' => $identity . 'PWD',
                ];

                if ($user->chat_user_id) {
                    $result = RocketChatHelper::updateUser($user->chat_user_id, $data);

                    if ($result) {
                        // Update user identity and chat password.
                        User::where('id', $user->id)->update([
                            'identity' => $identity,
                            'chat_password' => hash('sha256', $data['password']),
                        ]);
                    }
                }
            }
        }

        $this->info('Therapist identity has been updated successfully');
    }
}
