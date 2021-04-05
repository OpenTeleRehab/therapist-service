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
        $therapistTotal = User::all()->count();
        $therapistsByCountry = DB::table('users')
            ->select(DB::raw('
                country_id,
                COUNT(*) AS total
            '))->groupBy('country_id')
            ->get();

        return [
            'therapistTotal' => $therapistTotal,
            'therapistsByCountry' => $therapistsByCountry,
        ];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function getDataForCountryAdmin(Request $request)
    {
        $country_id = $request->get('country_id');
        $therapistTotal = User::where('country_id', $country_id)->count();
        $therapistsByClinic = DB::table('users')
            ->select(DB::raw('
                clinic_id,
                COUNT(*) AS total
            '))
            ->where('country_id', $country_id)
            ->groupBy('clinic_id')
            ->get();

        return [
            'therapistTotal' => $therapistTotal,
            'therapistsByClinic' => $therapistsByClinic,
        ];
    }
}
