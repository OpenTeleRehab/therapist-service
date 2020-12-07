<?php

namespace App\Http\Controllers;

use App\Http\Resources\TreatmentPlanResource;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;

class TreatmentPlanController extends Controller
{
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $info = [];
        if ($request->has('id')) {
            $treatmentPlans = TreatmentPlan::where('id', $request->get('id'))->get();
        } else {
            $query = TreatmentPlan::where('patient_id', $data['patient_id'])
                ->where(function ($query) use ($data) {
                    $query->where('name', 'like', '%' . $data['search_value'] . '%');
                });

            if (isset($data['filters'])) {
                $filters = $request->get('filters');
                $query->where(function ($query) use ($filters) {
                    foreach ($filters as $filter) {
                        $filterObj = json_decode($filter);
                        if ($filterObj->columnName === 'treatment_status') {
                            $query->where('status', trim($filterObj->value));
                        } elseif ($filterObj->columnName === 'start_date' || $filterObj->columnName === 'end_date') {
                            $dates = explode(' - ', $filterObj->value);
                            $startDate = date_create_from_format('d/m/Y', $dates[0]);
                            $endDate = date_create_from_format('d/m/Y', $dates[1]);
                            $startDate->format('Y-m-d');
                            $endDate->format('Y-m-d');
                            $query->where($filterObj->columnName, '>=', $startDate)
                                ->where($filterObj->columnName, '<=', $endDate);
                        } else {
                            $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                        }
                    }
                });
            }

            $treatmentPlans = $query->paginate($data['page_size']);
            $info = [
                'current_page' => $treatmentPlans->currentPage(),
                'total_count' => $treatmentPlans->total(),
            ];
        }

        return ['success' => true, 'data' => TreatmentPlanResource::collection($treatmentPlans), 'info' => $info];
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array|void
     */
    public function store(Request $request)
    {
        $type = $request->get('type');
        $name = $request->get('name');
        $description = $request->get('description');
        $patientId = $request->get('patient_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($type === TreatmentPlan::TYPE_PRESET) {
            $treatmentPlan = TreatmentPlan::updateOrCreate(
                [
                    'name' => $name,
                    'type' => TreatmentPlan::TYPE_PRESET,
                ],
                [
                    'description' => $description,
                ]
            );

            if (!$treatmentPlan) {
                return ['success' => false, 'message' => 'error_message.treatment_plan_add_as_preset'];
            }

            return ['success' => true, 'message' => 'success_message.treatment_plan_add_as_preset'];
        } else {
            $startDate = date_create_from_format(config('settings.date_format'), $startDate)->format('Y-m-d');
            $endDate = date_create_from_format(config('settings.date_format'), $endDate)->format('Y-m-d');

            // Check if there is any overlap schedule.
            $overlapRecords = TreatmentPlan::where('patient_id', $patientId)
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate]);
                })->get();

            if (count($overlapRecords)) {
                return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient_overlap_schedule'];
            }

            $treatmentPlan = TreatmentPlan::create([
                'name' => $name,
                'description' => $description,
                'type' => TreatmentPlan::TYPE_NORMAL,
                'patient_id' => $patientId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => TreatmentPlan::STATUS_PLANNED,
            ]);

            if (!$treatmentPlan) {
                return ['success' => false, 'message' => 'error_message.treatment_plan_assign_to_patient'];
            }

            return ['success' => true, 'message' => 'success_message.treatment_plan_assign_to_patient', 'data' => $treatmentPlan];
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\TreatmentPlan $treatmentPlan
     *
     * @return array
     */
    public function update(Request $request, TreatmentPlan $treatmentPlan)
    {
        $name = $request->get('name');
        $description = $request->get('description');
        $startDate = date_create_from_format(config('settings.date_format'), $request->get('start_date'))->format('Y-m-d');
        $endDate = date_create_from_format(config('settings.date_format'), $request->get('end_date'))->format('Y-m-d');

        $treatmentPlan->update([
            'name' => $name,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        return ['success' => true, 'message' => 'success_message.treatment_plan_update'];
    }
}
