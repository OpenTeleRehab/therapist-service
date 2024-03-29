<?php

namespace App\Http\Controllers;

use App\Http\Resources\TreatmentPlanResource;
use App\Models\Activity;
use App\Models\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

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
            $treatmentPlans = TreatmentPlan::where('created_by', Auth::id())
                ->where('id', $request->get('id'))
                ->get();
        } else {
            $query = TreatmentPlan::where('created_by', Auth::id());

            if (isset($data['search_value'])) {
                $query->where(function ($query) use ($data) {
                    $query->where('name', 'like', '%' . $data['search_value'] . '%');
                });
            }

            if (isset($data['filters'])) {
                $filters = $request->get('filters');
                $query->where(function ($query) use ($filters) {
                    foreach ($filters as $filter) {
                        $filterObj = json_decode($filter);
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
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
     * @return array
     */
    public function store(Request $request)
    {
        $treatmentPlan = TreatmentPlan::updateOrCreate(
            [
                'name' => $request->get('name'),
                'created_by' => Auth::id(),
            ],
            [
                'total_of_weeks' => $request->get('total_of_weeks', 1),
            ]
        );

        if (!$treatmentPlan) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_add_as_preset'];
        }

        $this->updateOrCreateActivities($treatmentPlan->id, $request->get('activities', []));
        return ['success' => true, 'message' => 'success_message.treatment_plan_add_as_preset'];
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\TreatmentPlan $treatmentPlan
     *
     * @return array
     */
    public function update(Request $request, TreatmentPlan $treatmentPlan)
    {
        if ($treatmentPlan->created_by !== Auth::id()) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_update'];
        }

        $treatmentPlan->update([
            'name' => $request->get('name'),
            'total_of_weeks' => $request->get('total_of_weeks', 1),
        ]);

        $this->updateOrCreateActivities($treatmentPlan->id, $request->get('activities', []));
        return ['success' => true, 'message' => 'success_message.treatment_plan_update'];
    }

    /**
     * @param int $treatmentPlanId
     * @param array $activities
     *
     * @return void
     */
    private function updateOrCreateActivities(int $treatmentPlanId, array $activities = [])
    {
        $activityIds = [];
        foreach ($activities as $activity) {
            $customExercises = isset($activity['customExercises']) ? $activity['customExercises'] : [];
            $exercises = $activity['exercises'];
            $materials = $activity['materials'];
            $questionnaires = $activity['questionnaires'];
            if (count($exercises) > 0) {
                foreach ($exercises as $exercise) {
                    $updateFields = [
                        'treatment_plan_id' => $treatmentPlanId,
                        'week' => $activity['week'],
                        'day' => $activity['day'],
                        'activity_id' => $exercise,
                        'type' => Activity::ACTIVITY_TYPE_EXERCISE,
                    ];

                    $customExercise = current(array_filter($customExercises, function ($c) use ($exercise) {
                        return $c['id'] === $exercise;
                    }));

                    if ($customExercise) {
                        $updateFields['sets'] = $customExercise['sets'];
                        $updateFields['reps'] = $customExercise['reps'];
                        $updateFields['additional_information'] = $customExercise['additional_information'] ?? null;
                    }

                    $activityObj = Activity::firstOrCreate($updateFields);
                    $activityIds[] = $activityObj->id;
                }
            }

            if (count($materials) > 0) {
                foreach ($materials as $material) {
                    $activityObj = Activity::firstOrCreate(
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $material,
                            'type' => Activity::ACTIVITY_TYPE_MATERIAL,
                        ],
                    );
                    $activityIds[] = $activityObj->id;
                }
            }

            if (count($questionnaires) > 0) {
                foreach ($questionnaires as $questionnaire) {
                    $activityObj = Activity::firstOrCreate(
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $questionnaire,
                            'type' => Activity::ACTIVITY_TYPE_QUESTIONNAIRE,
                        ],
                    );
                    $activityIds[] = $activityObj->id;
                }
                // TODO: move to Queued Event Listeners.
                Http::post(env('ADMIN_SERVICE_URL') . '/questionnaire/mark-as-used/by-ids', [
                    'questionnaire_ids' => $questionnaires,
                ]);
            }

            if (count($questionnaires) > 0) {
                foreach ($questionnaires as $questionnaire) {
                    $activityObj = Activity::firstOrCreate(
                        [
                            'treatment_plan_id' => $treatmentPlanId,
                            'week' => $activity['week'],
                            'day' => $activity['day'],
                            'activity_id' => $questionnaire,
                            'type' => Activity::ACTIVITY_TYPE_QUESTIONNAIRE,
                        ],
                    );
                    $activityIds[] = $activityObj->id;
                }
            }
        }

        // Remove not selected activities.
        Activity::where('treatment_plan_id', $treatmentPlanId)
            ->whereNotIn('id', $activityIds)
            ->delete();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getActivities(Request $request)
    {
        $result = [];
        $treatmentPlan = TreatmentPlan::where('created_by', Auth::id())
            ->where('id', $request->get('id'))
            ->firstOrFail();
        $activities = $treatmentPlan->activities->sortBy(function ($activity) {
            return [$activity->week, $activity->day];
        });

        foreach ($activities as $key => $activity) {
            $activityObj = [];
            $response = null;

            if ($activity->type === Activity::ACTIVITY_TYPE_EXERCISE) {
                $response = Http::get(env('ADMIN_SERVICE_URL') . '/exercise/list/by-ids', [
                    'exercise_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang')
                ]);
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_MATERIAL) {
                $response = Http::get(env('ADMIN_SERVICE_URL') . '/education-material/list/by-ids', [
                    'material_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang')
                ]);
            } elseif ($activity->type === Activity::ACTIVITY_TYPE_QUESTIONNAIRE) {
                $response = Http::get(env('ADMIN_SERVICE_URL') . '/questionnaire/list/by-ids', [
                    'questionnaire_ids' => [$activity->activity_id],
                    'lang' => $request->get('lang')
                ]);
            }

            if (!empty($response) && $response->successful()) {
                if ($response->json()['data']) {
                    $activityObj = $response->json()['data'][0];
                    $activityObj['id'] = $activity->id;

                    // Custom Sets/Reps in Treatment.
                    if ($activity->sets !== null) {
                        $activityObj['sets'] = $activity->sets;
                        $activityObj['custom'] = true;
                    }
                    if ($activity->reps !== null) {
                        $activityObj['reps'] = $activity->reps;
                        $activityObj['custom'] = true;
                    }
                } else {
                    continue;
                }
            }

            $result[] = array_merge([
                'activity_id' => $activity->activity_id,
                'completed' => $activity->completed,
                'pain_level' => $activity->pain_level,
                'sets' => $activity->sets,
                'reps' => $activity->reps,
                'satisfaction' => $activity->satisfaction,
                'type' => $activity->type,
                'submitted_date' => $activity->submitted_date,
                'answers' => [],
                'week' => $activity->week,
                'day' => $activity->day,
            ], $activityObj);
        }

        $data = array_merge($treatmentPlan->toArray(), ['activities' => $result]);

        return ['success' => true, 'data' => $data];
    }

    /**
     * @param \App\Models\TreatmentPlan $treatmentPlan
     *
     * @return array
     * @throws \Exception
     */
    public function destroy(TreatmentPlan $treatmentPlan)
    {
        if ($treatmentPlan->created_by !== Auth::id()) {
            return ['success' => false, 'message' => 'error_message.treatment_plan_delete'];
        }

        $treatmentPlan->delete();
        return ['success' => true, 'message' => 'success_message.treatment_plan_delete'];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function countTherapistTreatmentPlan(Request $request)
    {
        $therapistId = $request->get('therapist_id');
        $totalTreatmentPlans = TreatmentPlan::where('created_by', $therapistId)->count();

        return $totalTreatmentPlans;
    }
}
