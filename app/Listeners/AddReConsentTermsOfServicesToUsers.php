<?php

namespace App\Listeners;

use App\Events\AddReConsentTermsOfServices;
use App\Helpers\KeycloakHelper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_EXECUTE_EMAIL", '/execute-actions-email?client_id=' . env('KEYCLOAK_BACKEND_CLIENT') . '&redirect_uri=' . env('REACT_APP_BASE_URL'));

class AddReConsentTermsOfServicesToUsers
{

    /**
     * Handle the event.
     *
     * @param  AddReConsentTermsOfServices  $event
     * @return void
     */
    public function handle(AddReConsentTermsOfServices $event)
    {
        $token = KeycloakHelper::getKeycloakAccessToken();
        $requiredActions = $event->user['requiredActions'];
        array_push($requiredActions, 'terms_and_conditions');

        $url = KEYCLOAK_USER_URL . '/'. $event->user["id"];
        Http::withToken($token)->put($url, [
            'requiredActions' => array_unique($requiredActions),
        ]);
    }
}
