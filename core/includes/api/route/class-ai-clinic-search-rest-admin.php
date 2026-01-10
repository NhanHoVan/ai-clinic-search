<?php
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_REST_Admin
 *
 * Admin-facing REST API endpoints
 */
class Ai_Clinic_REST_Admin
{

    public function register_routes()
    {
        $base = Ai_Clinic_REST_Register::get_base(Ai_Clinic_REST_Register::GROUP_ADMIN);
        $clinic_controller = new AI_Clinic_Controller_Clinic();

        // get_clinic_by_id
        register_rest_route($base, '/clinic', [
            'methods' => 'GET',
            'callback' => [$clinic_controller, 'get_clinic_by_id'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // update_clinic
        register_rest_route($base, '/clinic/update', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'update_clinic'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // delete_clinic
        register_rest_route($base, '/clinic/delete', [
            'methods' => 'DELETE',
            'callback' => [$clinic_controller, 'delete_clinic'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // create_clinic_suggestion
        register_rest_route($base, '/clinic/suggestions', [
            'methods'  => 'POST',
            'callback' => [$clinic_controller, 'create_clinic_suggestion'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // get_clinics_management
        register_rest_route($base, '/clinics-management', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'get_clinics_management'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // get_clinic_suggestions
        register_rest_route($base, '/clinic_suggestions', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'get_clinic_suggestions'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);

        // review_clinic_suggestions
        register_rest_route($base, '/clinic_suggestions/review', [
            'methods' => 'POST',
            'callback' => [$clinic_controller, 'review_clinic_suggestions'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);
    }
}
