<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Controller_Clinic
 *
 * Handles logic for clinic endpoints
 * 
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Controller_Clinic
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Controller_Clinic
{
    /**
     * @var Ai_Clinic_Service_Location Location instance
     */
    private $location_service;

    /**
     * @var Ai_Clinic_Search_DB Database instance
     */
    private $database;

    /**
     * @var Ai_Clinic_Service_Google_Search GoogleSearch instance
     */
    private $google_search;

    /**
     * @var Ai_Clinic_Service_OpenAI OpenAI instance
     */
    private $openai;

    /**
     * helper
     *
     * @var		Ai_Clinic_Search_Helpers
     * @since   1.0.0
     */
    private $helper;

    public function __construct()
    {
        $this->location_service = new Ai_Clinic_Service_Location();
        $this->database = new Ai_Clinic_Search_DB();
        $this->google_search = new Ai_Clinic_Service_Google_Search();
        $this->openai = new Ai_Clinic_Service_OpenAI();
        $this->helper = new Ai_Clinic_Search_Helpers();
    }

    /**
     * GET ai-clinic-search/provinces
     */
    public function get_provinces($request)
    {
        $search = sanitize_text_field($request->get_param('search'));

        $provinces = $this->location_service->get_all_provinces($search);

        if (empty($provinces)) {
            return new WP_REST_Response(['message' => 'No provinces found.'], 200);
        }

        return new WP_REST_Response($provinces, 200);
    }

    /**
     * GET ai-clinic-search/districts?province_code=1
     */
    public function get_districts($request)
    {
        $province_code = sanitize_text_field($request->get_param('province_code'));
        $search = sanitize_text_field($request->get_param('search'));

        if (empty($province_code)) {
            return new WP_REST_Response(['error' => 'province_code is required'], 400);
        }

        $districts = $this->location_service->get_districts_by_province($province_code, $search);

        if (empty($districts)) {
            return new WP_REST_Response(['error' => 'Province not found or has no districts'], 404);
        }

        return new WP_REST_Response($districts, 200);
    }


    public function get_specialties($request)
    {
        // TODO
    }

    public function get_refer_clinics($request)
    {
        // TODO remove return dymmy data
        $schema_file = CLINIC_SEARCH_PLUGIN_DIR . 'schemas/dummy_clinics.json';
        if (!file_exists($schema_file)) {
            throw new Exception('Schema file not found: dummy_clinics.json');
        }
        $json_schema = json_decode(file_get_contents($schema_file), true);
        if (!$json_schema) {
            throw new Exception('Invalid JSON schema file: dummy_clinics.json');
        }

        return new WP_REST_Response([
            'success' => true,
            'page' => 1,
            'total' => 15,
            'clinics' => $json_schema,
        ], 200);
    }

    /**
     * POST ai-clinic-search/clinics/search
     */
    public function search_clinics($request)
    {
        $body = $request->get_json_params();

        $search   = sanitize_text_field($body['search'] ?? '');
        $province_code = sanitize_text_field($body['province'] ?? '');
        $district_code = sanitize_text_field($body['district'] ?? '');

        if (empty($search)) {
            return new WP_REST_Response(['error' => 'Field "search" is required'], 400);
        }

        if (empty($province_code)) {
            return new WP_REST_Response(['error' => 'Field "province" is required'], 400);
        }

        if (empty($district_code)) {
            return new WP_REST_Response(['error' => 'Field "district" is required'], 400);
        }

        $province_name = $this->location_service->get_province_name_by_code($province_code);
        $district_name = $this->location_service->get_district_name_by_code($province_code, $district_code);

        try {
            $search_query = trim(sprintf(
                '%s tại %s, %s',
                $search,
                $district_name,
                $province_name
            ));
            $search_results = $this->google_search->search($search_query);

            if (empty($search_results['clinics'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'No results found from Search',
                    'keyword' => $search,
                    'clinics' => []
                ], 200);
            }

            $data = $this->openai->extractionClinics($search_results['clinics']);

            $saved_clinics = [];
            foreach ($data['clinics'] as $clinic) {
                if (empty($clinic['name'])) {
                    continue;
                }

                $clinic_update = [
                    'name'          => $clinic['name'],
                    'place_id'      => $clinic['place_id'] ?? '',
                    'address'       => $clinic['address'] ?? '',
                    'province'      => $province_code,
                    'district'      => $district_code,
                    'lat'           => $clinic['lat'] ?? null,
                    'lng'           => $clinic['lng'] ?? null,
                    'phone'         => $clinic['phone'] ?? '',
                    'email'         => $clinic['email'] ?? '',
                    'website'       => $clinic['website'] ?? '',
                    'description'   => $clinic['description'] ?? '',
                    'verify_status' => 'AI_DETECTED',
                    'is_active'     => 'ACTIVE'
                ];

                // Check if clinic already exists
                $existing = $this->checkClinicExists($clinic_update);
                if ($existing) {
                    $saved_clinics[] = $existing;
                    continue;
                }

                // Insert new clinic
                $clinic_id = $this->database->aiclinic_insert_clinic($clinic_update);
                if ($clinic_id) {
                    $result = $this->database->aiclinic_get_clinics([
                        'filters' => ['id' => $clinic_id]
                    ]);
                    if ($result) {
                        $saved_clinics[] = $result[0];
                    }
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'keyword' => $search,
                'total_found' => count($saved_clinics),
                'clinics' => $saved_clinics,
            ], 200);
        } catch (Exception $e) {
            error_log('Search clinics error: ' . $e->getMessage());

            return new WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage(),
                'keyword' => $search,
                'clinics' => []
            ], 500);
        }
    }

    /**
     * Check if clinic already exists in DB.
     *
     * @param array $clinic Clinic data from AI
     * @param object $database Your database object
     * @return array|null Returns clinic data that already exists, or null if not yet exists
     */
    private function checkClinicExists($clinic)
    {
        $existing = null;
        if (!empty($clinic['phone'])) {
            $phone = $this->helper->phone_number->normalize_phone($clinic['phone']);
            $existing = $this->database->aiclinic_get_clinics_by_phone($phone);
            if (!empty($existing)) {
                return $existing[0];
            }
        }

        if (!empty($clinic['name'])) {
            $existing = $this->database->aiclinic_get_clinics_by_name_and_province($clinic['name'], $clinic['province']);
            if (!empty($existing)) {
                return $existing[0];
            }
        }

        return $existing;
    }
}
