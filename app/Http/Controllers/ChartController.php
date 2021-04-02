<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
}
