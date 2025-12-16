<?php

namespace App\Http\Controllers;

use App\Helpers\KeycloakHelper;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;

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
}
