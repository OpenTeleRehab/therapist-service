<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Requests\CreateFirebaseTokenRequest;
use App\Http\Requests\DeleteFirebaseTokenRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\Device;
use Illuminate\Http\Request;
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

        $fcmToken = $request->get('firebase_token');
        $deviceId = $request->get('device_id');

        // Create or update the device record with the new token and device ID.
        Device::updateOrCreate([
            'device_id' => $deviceId,
        ], [
            'user_id' => $user->id,
            'fcm_token' => $fcmToken,
        ]);

        // Delete any other device records with the same token or device ID that do not belong to the current user.
        Device::whereNot('user_id', $user->id)
            ->where(function ($query) use ($fcmToken, $deviceId) {
                $query->where('fcm_token', $fcmToken)
                    ->orWhere('device_id', $deviceId);
            })
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['firebase_token' => $fcmToken],
        ]);
    }

    /**
     * @param DeleteFirebaseTokenRequest $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteFirebaseToken(Request $request)
    {
        $fcmToken = $request->get('firebase_token');
        $deviceId = $request->get('device_id');

        Device::where('fcm_token', $fcmToken)
            ->orWhere('device_id', $deviceId)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'common.delete_firebase_token_success',
        ]);
    }
}
