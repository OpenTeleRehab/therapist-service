<?php

namespace App\Console\Commands;

use App\Helpers\RocketChatHelper;
use App\Models\User;
use Illuminate\Console\Command;

class MigrateRocketChatRooms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     *
     */
    protected $signature = 'hi:migrate-rocketchat-rooms';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate rocketchat rooms';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return boolean
     */
    public function handle()
    {
        User::whereNotNull('identity')
            ->get()
            ->groupBy('clinic_id')
            ->map(function ($therapists) {
                if ($therapists->isNotEmpty()) {
                    for ($i = 0; $i < count($therapists); $i++) {
                        for ($j = $i + 1; $j < count($therapists); $j++) {
                            try {
                                RocketChatHelper::createChatRoom($therapists[$i]['identity'], $therapists[$j]['identity']);
                                sleep(30); // To avoid too many queries, delay by 30 seconds.
                            } catch (\Exception $e) {
                                return $e->getMessage();
                            }
                        }
                    }
                }
            });

        $this->info('Rocketchat room has been created successfully');

        return true;
    }
}
