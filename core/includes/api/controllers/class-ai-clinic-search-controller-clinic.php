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
     * @var Ai_Clinic_Service_Clinic Clinic instance
     */
    private $clinic_service;

    /**
     * @var Ai_Clinic_Service_Clinic_Suggest Clinic instance
     */
    private $clinic_suggest_service;

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
        $this->clinic_service = new Ai_Clinic_Service_Clinic();
        $this->clinic_suggest_service = new Ai_Clinic_Service_Clinic_Suggest();
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

    /**
     * GET ai-clinic-search/specialties
     */
    public function get_specialties()
    {
        $specialties = [];
        try {
            $result = $this->database->aiclinic_get_tags();

            $specialties = array_map(function ($item) {
                return [
                    'id'   => (string) $item['id'],
                    'name' => $item['name'],
                ];
            }, $result);
        } catch (Exception $e) {
            error_log('Search specialties error: ' . $e->getMessage());
        }

        return new WP_REST_Response([
            'success' => true,
            'specialties' => $specialties,
        ], 200);
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
            'is_active' => 'ACTIVE',
        ]);

        $clinics_filtered = $this->clinic_service->filter_and_mask_clinics($result['data'] ?? []);

        return new WP_REST_Response([
            'success' => true,
            'page'    => $body['page'],
            'total'   => $result['filtered'] ?? 0,
            'clinics' => $clinics_filtered,
        ], 200);
    }

    /**
     * GET ai-clinic-search/clinic
     */
    public function get_clinic_by_id($request)
    {
        $source = $request->get_header('X-INTERNAL-SOURCE');
        $user_id = null;
        if ($source === AI_CLINIC_SEARCH_INTERNAL_SOURCE) {
            $user_id = absint($request->get_header('X-INTERNAL-USER-ID'));
        }

        $clinic_id = sanitize_text_field($request->get_param('id'));

        if (empty($clinic_id)) {
            return new WP_Error(
                'invalid_id',
                'id is required',
                ['status' => 400]
            );
        }

        $clinic_id = absint($clinic_id);
        $result = $this->database->aiclinic_get_clinic_by_id($clinic_id);

        return new WP_REST_Response($result, 200);
    }

    /**
     * Handle clinic search request and return matched clinics.
     *
     * Route: POST ai-clinic-search/clinics/search
     *
     * @param WP_REST_Request $request The REST request object.
     *
     * @return WP_REST_Response
     *         200: Search executed successfully.
     *         400: Missing required parameters.
     *         500: Internal server error.
     */
    public function search_clinics($request)
    {
        $body = $request->get_json_params();

        $search        = sanitize_text_field($body['search'] ?? '');
        $province_code = sanitize_text_field($body['province'] ?? '');
        $district_code = sanitize_text_field($body['district'] ?? '');

        if (empty($search) || empty($province_code) || empty($district_code)) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Fields "search", "province", and "district" are required',
            ], 400);
        }

        try {
            $result = $this->clinic_service->searchAndUpsertClinics(
                $search,
                $province_code,
                $district_code
            );

            return new WP_REST_Response($result, 200);
        } catch (Exception $e) {
            error_log('Search clinics error: ' . $e->getMessage());

            return new WP_REST_Response([
                'success' => false,
                'error'   => $e->getMessage(),
                'keyword' => $search,
                'clinics' => [],
            ], 500);
        }
    }

    /**
     * Get clinics management (user/admin)
     */
    public function get_clinics_management(WP_REST_Request $request)
    {
        $body_request = $request->get_json_params();
        $body = [
            'province' => sanitize_text_field($body_request['province'] ?? ''),
            'district' => sanitize_text_field($body_request['district'] ?? ''),
            'limit'    => intval($body_request['limit'] ?? 8),
            'page'     => max(1, intval($body_request['page'] ?? 1)),
            'type'     => sanitize_text_field($body_request['type'] ?? ''),
            'search'   => sanitize_text_field($body_request['search'] ?? ''),
        ];

        $offset = ($body['page'] - 1) * $body['limit'];

        $db_params = [
            'province' => $body['province'],
            'district' => $body['district'],
            'limit'    => $body['limit'],
            'offset'   => $offset,
        ];

        if (!empty($body['search'])) {
            $db_params['search'] = $body['search'];
        }

        if ($body['type'] === 'management-user') {
            $db_params['is_active'] = 'ACTIVE';
            $result = $this->database->aiclinic_get_clinics_paginated($db_params);
            $clinics_filtered = $this->clinic_service->filter_and_mask_clinics($result['data'] ?? []);
            $clinics_response = $this->clinic_suggest_service->map_user_clinics_with_suggestions($clinics_filtered);
        } elseif ($body['type'] === 'management-admin') {
            $result = $this->database->aiclinic_get_clinics_paginated($db_params);
            $clinics_response = $this->clinic_service->filter_and_mask_clinics($result['data'] ?? []);
        } else {
            return new WP_Error('invalid_request', 'Invalid type', ['status' => 400]);
        }

        return new WP_REST_Response([
            'success' => true,
            'page'    => $body['page'],
            'total'   => $result['filtered'] ?? 0,
            'clinics' => $clinics_response,
        ], 200);
    }

    /**
     * POST ai-clinic-search/clinic/update
     */
    public function update_clinic(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $clinic_id = isset($body['clinic_id']) ? absint($body['clinic_id']) : 0;
        $update_fields = $body['update_fields'] ?? null;

        if ($clinic_id <= 0) {
            return new WP_Error(
                'invalid_clinic_id',
                'clinic_id is required',
                ['status' => 400]
            );
        }

        if (empty($update_fields) || !is_array($update_fields)) {
            return new WP_Error(
                'invalid_update_fields',
                'update_fields must be a non-empty array',
                ['status' => 400]
            );
        }

        $result = $this->database->aiclinic_update_clinic($clinic_id, $update_fields);
        if ($result === false) {
            return new WP_Error(
                'update_failed',
                'Failed to update clinic',
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'clinic_id' => $clinic_id,
            'updated'   => $result,
        ], 200);
    }

    /**
     * DELETE ai-clinic-search/clinic/delete
     */
    public function delete_clinic(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $clinic_id = isset($body['clinic_id']) ? absint($body['clinic_id']) : 0;

        if ($clinic_id <= 0) {
            return new WP_Error(
                'invalid_clinic_id',
                'clinic_id is required',
                ['status' => 400]
            );
        }

        $result = $this->database->aiclinic_delete_clinic($clinic_id);

        if ($result === false) {
            return new WP_Error(
                'delete_failed',
                'Failed to delete clinic',
                ['status' => 500]
            );
        }

        return new WP_REST_Response([
            'clinic_id' => $clinic_id,
            'deleted'   => $result,
        ], 200);
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
        $source = $request->get_header('X-INTERNAL-SOURCE');
        $user_id = null;
        if ($source === AI_CLINIC_SEARCH_INTERNAL_SOURCE) {
            $user_id = absint($request->get_header('X-INTERNAL-USER-ID'));
        }

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

            $clinicIsExists = $this->clinic_service->clinic_exists((int) $params['clinic_id']);
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
            'user_id'          => $user_id,
            'suggested_by'     => $params['suggested_by'] ?? 'OTHER',
            'suggested_fields' => $this->sanitize_suggested_fields(
                $params['suggested_fields']
            ),
        ];

        $suggestion_id = $this->clinic_suggest_service->create_clinic_suggestion($data);
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

    /**
     * POST ai-clinic-search/clinic_suggestions
     */
    public function get_clinic_suggestions($request)
    {
        $body_request = $request->get_json_params();
        $body = [
            'limit'    => intval($body_request['limit'] ?? 8),
            'page'     => max(1, intval($body_request['page'] ?? 1)),
        ];

        $offset = ($body['page'] - 1) * $body['limit'];

        $result = $this->database->aiclinic_get_clinic_edit_suggestions_paginated([
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
     * POST ai-clinic-search/review_clinic_suggestions
     */
    public function review_clinic_suggestions(WP_REST_Request $request)
    {
        $source = $request->get_header('X-INTERNAL-SOURCE');
        $user_id = null;
        if ($source === AI_CLINIC_SEARCH_INTERNAL_SOURCE) {
            $user_id = absint($request->get_header('X-INTERNAL-USER-ID'));
        }

        if (empty($user_id)) {
            return new WP_Error(
                'unauthorized',
                'User is not authorized',
                ['status' => 401]
            );
        }

        $body = $request->get_json_params();
        $suggest_id = isset($body['suggest_id']) ? absint($body['suggest_id']) : 0;
        $review_fields = $body['review_fields'] ?? null;

        if ($suggest_id <= 0) {
            return new WP_Error(
                'invalid_suggest_id',
                'id is required',
                ['status' => 400]
            );
        }

        if (empty($review_fields) || !is_array($review_fields)) {
            return new WP_Error(
                'invalid_review_fields',
                'review_fields must be an array',
                ['status' => 400]
            );
        }

        if (empty($review_fields['status']) || !in_array($review_fields['status'], ['APPROVED', 'REJECTED'], true)) {
            return new WP_Error(
                'invalid_status',
                'status must be APPROVED or REJECTED',
                ['status' => 400]
            );
        }

        $result = $this->clinic_suggest_service->review_suggestion(
            $suggest_id,
            $review_fields,
            $user_id
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }
}
