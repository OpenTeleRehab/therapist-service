<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Requests\CreateFirebaseTokenRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\Device;
use Illuminate\Support\Facades\Auth;

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

    /**
     * @param ForgotPasswordRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $response = KeycloakHelper::forgetUserPassword($request->get('email'));

        if ($response) {
            return response()->json([
                'success' => true,
                'message' => 'common.check_email_description',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'common.sent_email_error',
        ]);
    }

    /**
     * @param CreateFirebaseTokenRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createFirebaseToken(CreateFirebaseTokenRequest $request)
    {
        $user = Auth::user();

        $token = $request->get('firebase_token');

        Device::where('fcm_token', $token)
            ->whereNot('user_id', $user->id)
            ->delete();

        Device::updateOrCreate([
            'user_id' => $user->id,
            'fcm_token' => $request->get('firebase_token'),
        ]);

        return response()->json([
            'success' => true,
            'data' => ['firebase_token' => $request->get('firebase_token')],
        ]);
    }
}
