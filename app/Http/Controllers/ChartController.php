<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/chart/get-data-for-global-admin",
     *     tags={"Chart Data"},
     *     summary="Get data for global admin",
     *     operationId="getDataForGlobalAdmin",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
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
     * @OA\Get(
     *     path="/api/chart/get-data-for-country-admin",
     *     tags={"Chart Data"},
     *     summary="Get data for country admin",
     *     operationId="getDataForCountryAdmin",
     *     @OA\Parameter(
     *         name="country_id",
     *         in="query",
     *         description="Country id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
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
     * @OA\Get(
     *     path="/api/chart/get-data-for-clinic-admin",
     *     tags={"Chart Data"},
     *     summary="Get data for clinic admin",
     *     operationId="getDataForClinicAdmin",
     *     @OA\Parameter(
     *         name="clinic_id",
     *         in="query",
     *         description="Clinic id",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
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
