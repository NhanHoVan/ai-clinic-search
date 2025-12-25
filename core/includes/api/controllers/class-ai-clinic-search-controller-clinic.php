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

    /**
     * POST ai-clinic-search/clinics
     */
    public function get_refer_clinics($request)
    {
        $body_request = $request->get_json_params();
        $body = [
            'province' => sanitize_text_field($body_request['province'] ?? ''),
            'district' => sanitize_text_field($body_request['district'] ?? ''),
            'limit'    => intval($body_request['limit'] ?? 8),
            'page'     => max(1, intval($body_request['page'] ?? 1)),
        ];

        $offset = ($body['page'] - 1) * $body['limit'];

        $result = $this->database->aiclinic_get_clinics_paginated([
            'province' => $body['province'],
            'district' => $body['district'],
            'limit'    => $body['limit'],
            'offset'   => $offset,
        ]);

        return new WP_REST_Response([
            'success' => true,
            'page'    => $body['page'],
            'total'   => $result['filtered'] ?? 0,
            'clinics' => $result['data'] ?? [],
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

    /**
     * Create a clinic suggestion (CREATE or EDIT).
     *
     * @param WP_REST_Request $request Incoming REST request containing:
     *     @type string       $suggestion_type  Suggestion type: CREATE | EDIT
     *     @type int|null     $clinic_id        Required when suggestion_type is EDIT
     *     @type string|null  $suggested_by     Source of suggestion: OWNER | OTHER
     *     @type array        $suggested_fields Proposed clinic data
     *
     * @return WP_REST_Response|WP_Error
     *     On success:
     *     @type bool   $success
     *     @type int    $suggestion_id
     *     @type string $status           PENDING
     *
     *     On failure:
     *     WP_Error with appropriate status code
     */
    public function create_clinic_suggestion(WP_REST_Request $request)
    {
        $params = $request->get_json_params();

        $suggestion_type = $params['suggestion_type'] ?? null;
        if (!in_array($suggestion_type, ['CREATE', 'EDIT'], true)) {
            return new WP_Error(
                'invalid_suggestion_type',
                'Invalid suggestion_type',
                ['status' => 400]
            );
        }

        if (
            empty($params['suggested_fields']) ||
            !is_array($params['suggested_fields'])
        ) {
            return new WP_Error(
                'invalid_suggested_fields',
                'suggested_fields is required',
                ['status' => 400]
            );
        }

        if ($suggestion_type === 'EDIT') {
            if (empty($params['clinic_id'])) {
                return new WP_Error(
                    'missing_clinic_id',
                    'clinic_id is required for EDIT suggestion',
                    ['status' => 400]
                );
            }

            // TODO check clinic tồn tại
            $clinicIsExists = true;
            if (!$clinicIsExists) {
                return new WP_Error(
                    'clinic_not_found',
                    'Clinic does not exist',
                    ['status' => 404]
                );
            }
        }

        if ($suggestion_type === 'CREATE') {
            if (
                empty($params['suggested_fields']['name']) ||
                empty($params['suggested_fields']['address'])
            ) {
                return new WP_Error(
                    'missing_required_fields',
                    'name and address are required for CREATE suggestion',
                    ['status' => 400]
                );
            }
        }

        $data = [
            'suggestion_type'  => $suggestion_type,
            'clinic_id'        => $suggestion_type === 'EDIT'
                ? (int)$params['clinic_id']
                : null,
            'suggested_by'     => $params['suggested_by'] ?? 'OTHER',
            'suggested_fields' => $this->sanitize_suggested_fields(
                $params['suggested_fields']
            ),
        ];

        // TODO Call service
        $suggestion_id = "";

        if (!$suggestion_id) {
            return new WP_Error(
                'create_failed',
                'Failed to create clinic suggestion',
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'success'       => true,
            'suggestion_id' => $suggestion_id,
            'status'        => 'PENDING',
        ]);
    }

    /**
     * Sanitize suggested clinic fields before storing them as a suggestion.
     *
     * @param array $fields Raw suggested fields received from the proxy API.
     *     @type string $place_id
     *     @type string $name
     *     @type string $address
     *     @type string $province
     *     @type string $district
     *     @type string $phone
     *     @type string $email
     *     @type string $website
     *     @type string $description
     *     @type string $image
     *
     * @return array Sanitized suggested fields, containing only allowed keys.
     */
    private function sanitize_suggested_fields(array $fields): array
    {
        $sanitized = [];

        foreach (self::ALLOWED_SUGGEST_FIELDS as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];

            switch ($key) {
                case 'name':
                case 'address':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;

                case 'description':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;

                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;

                case 'website':
                case 'image':
                    $sanitized[$key] = esc_url_raw($value);
                    break;

                case 'phone':
                    $sanitized[$key] = preg_replace('/[^0-9+\s]/', '', (string) $value);
                    break;

                case 'province':
                case 'district':
                    $sanitized[$key] = (string) $value;
                    break;

                case 'place_id':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
            }
        }
        return $sanitized;
    }

    private const ALLOWED_SUGGEST_FIELDS = [
        'place_id',
        'name',
        'address',
        'province',
        'district',
        'phone',
        'email',
        'website',
        'description',
        'image',
    ];
}
