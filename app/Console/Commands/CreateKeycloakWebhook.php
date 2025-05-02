<?php

namespace App\Console\Commands;

use App\Helpers\KeycloakHelper;
use Illuminate\Console\Command;

class CreateKeycloakWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hi:create-keycloak-webhook {path : The path of the webhook endpoint (e.g. /audit-logs)} {--eventTypes=* : The event types to subscribe to (e.g., access.LOGIN, access.LOGOUT)}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Keycloak webhook with a specific path and subscribed event types.';

    /**
     * The console command example helper.
     *
     * @var string
     */
    protected $help = 'php artisan hi:create-keycloak-webhook';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $path = $this->argument('path');
        $eventTypes = $this->option('eventTypes');
        if (empty($eventTypes)) {
            $this->error('You must specify at least one event type using --eventTypes option.');
            return 1;
        }
        $url = env('THERAPIST_SERVICE_URL') . $path;
        $response = KeycloakHelper::createWebhook($url, $eventTypes);
        if ($response) {
            $this->info('Webhook created successfully.');
        } else {
            $this->error('Failed to create webhook.');
        }
    }
}
