<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    /**
     * @return array
     */
    public function getDataForGlobalAdmin()
    {
        $therapistTotal = User::where('enabled', '=', 1)->count();
        $therapistsByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                COUNT(*) AS total
            '))
            ->where('enabled', '=', 1)
            ->groupBy('country_id')
            ->get();

        $data = [
            'therapistTotal' => $therapistTotal,
            'therapistsByCountry' => $therapistsByCountry,
        ];

        return ['success' => true, 'data' => $data];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getDataForCountryAdmin(Request $request)
    {
        $country_id = $request->get('country_id');
        $therapistTotal = User::where('country_id', $country_id)->where('enabled', '=', 1)->count();
        $therapistsByClinic = DB::table('users')
            ->select(DB::raw('
                clinic_id,
                COUNT(*) AS total
            '))
            ->where('country_id', $country_id)
            ->where('enabled', '=', 1)
            ->groupBy('clinic_id')
            ->get();

        return [
            'therapistTotal' => $therapistTotal,
            'therapistsByClinic' => $therapistsByClinic,
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getDataForClinicAdmin(Request $request)
    {
        $clinicId = $request->get('clinic_id');
        $therapistTotal = User::where('clinic_id', $clinicId)->where('enabled', '=', 1)->count();

        return [
            'therapistTotal' => $therapistTotal
        ];
    }
}
