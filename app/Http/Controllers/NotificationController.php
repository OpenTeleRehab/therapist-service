<?php

namespace App\Http\Controllers;

use App\Models\Forwarder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void|null
     */
    public function pushNotification(Request $request)
    {
        $identity = $request->get('identity');

        $accessToken = Forwarder::getAccessToken(Forwarder::PATIENT_SERVICE);

        if (str_starts_with($identity, 'PHC')) {
            $user = User::where('identity', $request->get('identity'))->firstOrFail();

            $user->devices()->pluck('fcm_token')->each(function ($fcm_token) use ($request, $accessToken) {
                Http::withToken($accessToken)->get(env('PATIENT_SERVICE_URL') . '/push-notification', [
                    ...$request->all(),
                    'fcm_token' => $fcm_token,
                ]);
            });
        }

        if (!str_starts_with($identity, 'PHC') && !str_starts_with($identity, 'T')) {
            Http::withToken($accessToken)->get(env('PATIENT_SERVICE_URL') . '/push-notification', $request->all());
        }
    }
}
