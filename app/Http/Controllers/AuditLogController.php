<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{

    /*
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        // Prepare the log data
        Activity::create([
            'log_name' => 'therapist_service',
            'properties' => [
                'attributes' => ['user_id' => $user->id],
            ],
            'event' => 'logout',
            'subject_id' => $user->id,
            'subject_type' => $user::class,
            'causer_id' => $user->id,
            'causer_type' => User::class,
            'description' => 'logout',
            'full_name' => $user->last_name . ' ' . $user->first_name,
            'clinic_id' => $user->clinic_id,
            'country_id' => $user->country_id,
            'group' => 'therapist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['success' => true];
    }
}
