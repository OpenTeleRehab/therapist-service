<?php

namespace App\Http\Controllers;

use App\Events\AddLogToAdminServiceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\AuditLogResource;
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
        $logName = $request->get('log_name');
        $type = $request->get('type');
        $storeData = [
            'attributes' => ['user_id' => $user->id]
        ];

        // Prepare the log data
        activity()
           ->withProperties($storeData)
           ->useLog($logName)
           ->log($type);

        // Activity log
        $lastLoggedActivity = Activity::all()->last();
        event(new AddLogToAdminServiceEvent($lastLoggedActivity, $user));
        return ['success' => true];
    }
}
