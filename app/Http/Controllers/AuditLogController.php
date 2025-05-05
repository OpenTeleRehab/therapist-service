<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{

    const KEYCLOAK_EVENT_TYPE_LOGIN = 'access.LOGIN';

    /*
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $auth = $request->get('authDetails');
        $details = $request->get('details');
        $user = User::where('email', $auth['username'])->first();
        $type = $request->get('type');

        // Check if user just refresh the page
        $isRefresh = isset($details['response_mode'], $details['response_type']) && !isset($details['custom_required_action']);
        
        if (!$isRefresh && $user && $user->email !== env('KEYCLOAK_BACKEND_USERNAME')) {
            Activity::create([
                'log_name' => 'therapist_service',
                'properties' => [
                    'attributes' => ['user_id' => $user->id],
                ],
                'event' => $type === self::KEYCLOAK_EVENT_TYPE_LOGIN ? 'login' : 'logout',
                'subject_id' => $user->id,
                'subject_type' => $user::class,
                'causer_id' => $user->id,
                'causer_type' => User::class,
                'description' => $type === self::KEYCLOAK_EVENT_TYPE_LOGIN ? 'login' : 'logout',
                'full_name' => $user->last_name . ' ' . $user->first_name,
                'clinic_id' => $user->clinic_id,
                'country_id' => $user->country_id,
                'group' => 'therapist',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['success' => true];
    }
}
