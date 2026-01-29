<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\KeycloakHelper;

class SetupKeycloakPermissions extends Command
{
    protected $signature = 'hi:keycloak-setup-permissions';
    protected $description = 'Create Keycloak roles and assign them to groups';

    public function handle()
    {
        $roles = [
            'view_patient',
            'view_clinic_therapist',
            'view_country_therapist',
            'get_call_token',
            'push_patient_notification',
            'delete_chat_room',
            'view_activity_list',
            'manage_treatment_plan',
            'manage_message',
            'manage_own_profile',
            'manage_transfer',
            'manage_category',
            'view_therapist_library',
            'view_country',
            'view_disease',
            'view_profession',
            'view_setting',
            'view_guidance_page',
            'manage_exercise',
            'manage_education_material',
            'manage_questionnaire',
            'view_assistive_technology',
            'manage_patient',
            'manage_appointment',
            'view_patient_activity',
            'manage_patient_treatment_plan',
            'manage_patient_assistive_technology',
            'export_treatment_plan',
            'view_notification',
            'manage_download_tracker',
            'export',
            'download_file',
            'view_dashboard',
            'view_region_list',
            'view_province_list',
            'view_phc_service_list',
            'view_phc_service_phc_worker',
            'view_phc_service_list',
            'setup_exercise',
            'setup_educational_material',
            'setup_questionnaire',
            'view_health_condition',
            'manage_patient_referral',
            'manage_patient_referral_assignment',
            'view_country_provinces',
            'view_screening_questionnaire_list',
            'submit_interview_screening_questionnaire',
            'view_interview_screening_questionnaire_history',
            'chat_with_therapist',
            'chat_with_phc_worker',
            'view_referral_therapist_list',
            'view_accepted_referral_phc_worker_list',
            'view_phc_workers',
            'manage_survey',
        ];
        $groupRoles = [
            'therapist' => [
                'view_patient',
                'view_clinic_therapist',
                'view_country_therapist',
                'get_call_token',
                'push_patient_notification',
                'delete_chat_room',
                'view_activity_list',
                'manage_treatment_plan',
                'manage_message',
                'manage_own_profile',
                'manage_transfer',
                'manage_category',
                'view_therapist_library',
                'view_country',
                'view_profession',
                'view_setting',
                'view_phc_workers',
                'view_guidance_page',
                'manage_exercise',
                'manage_education_material',
                'manage_questionnaire',
                'view_assistive_technology',
                'manage_patient',
                'manage_appointment',
                'view_patient_activity',
                'manage_patient_treatment_plan',
                'manage_patient_assistive_technology',
                'export_treatment_plan',
                'view_notification',
                'download_file',
                'view_dashboard',
                'view_region_list',
                'view_province_list',
                'manage_patient_referral_assignment',
                'chat_with_therapist',
                'chat_with_phc_worker',
                'view_accepted_referral_phc_worker_list',
                'view_health_condition',
                'manage_survey',
                'view_interview_screening_questionnaire_history',
            ],
            'phc_worker' => [
                'view_patient',
                'get_call_token',
                'push_patient_notification',
                'delete_chat_room',
                'view_activity_list',
                'manage_treatment_plan',
                'manage_message',
                'manage_own_profile',
                'view_country',
                'view_profession',
                'view_setting',
                'view_guidance_page',
                'view_assistive_technology',
                'manage_patient',
                'manage_appointment',
                'view_patient_activity',
                'manage_patient_treatment_plan',
                'manage_patient_assistive_technology',
                'manage_transfer',
                'export_treatment_plan',
                'view_notification',
                'download_file',
                'view_dashboard',
                'manage_exercise',
                'manage_education_material',
                'manage_questionnaire',
                'manage_category',
                'view_region_list',
                'view_province_list',
                'view_phc_service_list',
                'view_phc_service_phc_worker',
                'view_health_condition',
                'manage_patient_referral',
                'view_country_provinces',
                'view_screening_questionnaire_list',
                'submit_interview_screening_questionnaire',
                'view_interview_screening_questionnaire_history',
                'chat_with_therapist',
                'chat_with_phc_worker',
                'view_referral_therapist_list',
                'manage_survey',
            ],
        ];

        $this->line("Creating roles...");

        foreach ($roles as $role) {
            $created = KeycloakHelper::createRealmRole($role, "Role: $role");
            if ($created) {
                $this->info(" - Role '{$role}': Created");
            } else {
                $this->warn(" - Role '{$role}': Already exists or failed");
            }
        }

        $this->line("Creating groups and assigning roles to groups...");

        foreach ($groupRoles as $group => $roles) {
            $created = KeycloakHelper::createGroup($group);
            if ($created) {
                $this->info(" - Group '{$group}': Created");
            } else {
                $this->warn(" - Group '{$group}': Already exists or failed");
                // Remove all existing roles from group first
                try {
                    $this->info(" - Group '{$group}': Removing existing roles...");
                    KeycloakHelper::removeAllRealmRolesFromGroup($group);
                    $this->info(" - Group '{$group}': Existing roles removed");
                } catch (\Throwable $e) {
                    $this->error(" - Group '{$group}': {$e->getMessage()}");
                    continue;
                }
            }

            foreach ($roles as $role) {
                try {
                    $success = KeycloakHelper::assignRealmRoleToGroup($group, $role);
                    if ($success) {
                        $this->info(" - Group '{$group}' - Role '{$role}': Assigned");
                    } else {
                        $this->warn(" - Group '{$group}' - Role '{$role}': Failed");
                    }
                } catch (\Exception $e) {
                    $this->error("Error assigning role '{$role}' to group '{$group}': " . $e->getMessage());
                }
            }
        }

        $this->line("Permission setup complete.");
    }
}
