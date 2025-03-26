<?php
namespace App\Http\Controllers;

use Firebase\JWT\JWT as JWT;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SupersetController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $supersetUrl = env('SUPERSET_BASE_URL');
        $supersetAdmin = env('SUPERSET_ADMIN_USER');
        $supersetPassword = env('SUPERSET_ADMIN_PASSWORD');

        // Step 1: Login to Superset and get access token.
        $loginResponse = Http::post("$supersetUrl/api/v1/security/login", [
            'username' => $supersetAdmin,
            'password' => $supersetPassword,
            'provider' => 'db',
            'refresh' => true,
        ]);

        if (!$loginResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => $loginResponse->body(),
            ], 500);
        }

        $accessToken = $loginResponse->json()['access_token'];

        // Step 2: Get CSRF token and extract session cookies.
        $csrfResponse = Http::withToken($accessToken)
            ->withOptions(['verify' => false]) // Ignore SSL verification if needed.
            ->get("$supersetUrl/api/v1/security/csrf_token");

        if (!$csrfResponse->successful()) {
            return response()->json([
                'message' => $csrfResponse->body()
            ], 500);
        }

        $csrfToken = $csrfResponse->json()['result'];

        // Extract session cookies from the response.
        $cookies = [];
        foreach ($csrfResponse->cookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }

        $guestTokenPayload = [
            'user' => [
                'username' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name
            ],
            'resources' => [
                ['type' => 'dashboard', 'id' => null]
            ],
            'rls' => []
        ];

        $guestTokenPayload['resources'][0]['id'] = env('SUPERSET_DASHBOARD_ID_FOR_THERAPIST');
        $guestTokenPayload['rls'] = [
            ['clause' => "country_id = $user->country_id AND clinic_id = $user->clinic_id AND therapist_id = $user->id"]
        ];

        // Step 3: Request Guest Token from Superset, sending CSRF token and cookies.
        $guestResponse = Http::withHeaders([
            'X-CSRFToken' => $csrfToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->withToken($accessToken)
        ->withCookies($cookies, parse_url($supersetUrl, PHP_URL_HOST)) // Attach session cookies.
        ->post("$supersetUrl/api/v1/security/guest_token", $guestTokenPayload);

        if (!$guestResponse->successful()) {
            return response()->json([
                'success' => false,
                'message' => $guestResponse->body(),
            ], 500);
        }

        // Step 4: Decode the guest token and extract expiration time.
        $guestToken = $guestResponse->json()['token'];

        // Decode the JWT token to get payload
        $tokenParts = explode('.', $guestToken); // Split the JWT into parts
        if (count($tokenParts) === 3) {
            // Decode the payload part (index 1)
            $payload = JWT::urlsafeB64Decode($tokenParts[1]);

            // Convert the payload from JSON into an array
            $payloadData = json_decode($payload, true);

            // Extract expiration time (exp) from the payload
            $expirationTime = isset($payloadData['exp']) ? $payloadData['exp'] : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'guest_token' => $guestToken,
                'expiration_time' => $expirationTime,
            ],
        ]);
    }
}

