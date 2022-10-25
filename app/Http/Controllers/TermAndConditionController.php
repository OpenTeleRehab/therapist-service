<?php

namespace App\Http\Controllers;

use App\Events\AddReConsentTermsOfServices;
use App\Helpers\KeycloakHelper;
use Illuminate\Support\Facades\Http;

define("KEYCLOAK_USERS", env('KEYCLOAK_URL') . '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') . '/users');

class TermAndConditionController extends Controller
{
    /**
     * @return mixed
     */
    public function addReConsentTermsOfServicesToUsers()
    {
        try {
            $token = KeycloakHelper::getKeycloakAccessToken();
            $response = Http::withToken($token)->get(KEYCLOAK_USERS);

            foreach ($response->json() as $user) {
                if ($user['username'] !== env('KEYCLOAK_BACKEND_CLIENT')) {
                    event(new AddReConsentTermsOfServices($user));
                }
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
