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

        // create_clinic_suggestion
        register_rest_route($base, '/clinic/suggestions', [
            'methods'  => 'POST',
            'callback' => [$clinic_controller, 'create_clinic_suggestion'],
            'permission_callback' => [Ai_Clinic_Permissions::class, 'check_internal_api_key'],
        ]);
    }
}
