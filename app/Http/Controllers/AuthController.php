<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $response = KeycloakHelper::getLoginUser($request->get('email'), $request->get('password'));

        if ($response->successful()) {
            return response()->json([
                'success' => true,
                'data' => json_decode($response->body(), true),
            ]);
        }

        return response()->json([
            'success' => false,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $tokenResponse = Http::asForm()->post(KeycloakHelper::getTokenUrl(), [
            'grant_type' => 'client_credentials',
            'client_id' => env('KEYCLOAK_BACKEND_CLIENT'),
            'client_secret' => env('KEYCLOAK_BACKEND_SECRET'),
        ]);

        if (!$tokenResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'common.keycloak_auth_failed',
            ]);
        }

        $adminToken = $tokenResponse->json()['access_token'];

        $usersUrl = env('KEYCLOAK_URL') .
            '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') .
            '/users?email=' . urlencode($request->get('email'));

        $usersResponse = Http::withToken($adminToken)->get($usersUrl);

        $users = $usersResponse->json();

        if (empty($users)) {
            return response()->json([
                'success' => false,
                'message' => 'common.email_not_exist'
            ]);
        }

        $userId = $users[0]['id'];

        $executeUrl = env('KEYCLOAK_URL') .
            '/auth/admin/realms/' . env('KEYCLOAK_REAMLS_NAME') .
            '/users/' . $userId . '/execute-actions-email';

        $response = Http::withToken($adminToken)->put(
            $executeUrl,
            ['UPDATE_PASSWORD']
        );

        return response()->json([
            'success' => $response->successful(),
            'message' => $response->successful()
                ? 'common.check_email_description'
                : 'common.sent_email_error',
            'keycloak_response' => $response->json()
        ]);
    }
}
