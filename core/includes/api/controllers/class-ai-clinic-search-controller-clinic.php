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
    private $location_service;

    public function __construct()
    {
        $this->location_service = new Ai_Clinic_Service_Location();
    }

    /**
     * POST ai-clinic-search/clinics/search
     */
    public function search_clinics($request)
    {
        $params = $request->get_json_params();
        $keyword = sanitize_text_field($params['keyword'] ?? '');
        $province = sanitize_text_field($params['province'] ?? '');

        if (empty($keyword)) {
            return new WP_REST_Response(['error' => 'Keyword is required'], 400);
        }

        // 1️⃣ Gọi Google Search API (tạm thời mock)
        // 2️⃣ Gọi AI parser service
        // 3️⃣ Lưu dữ liệu phòng khám vào DB
        // 4️⃣ Trả về danh sách đã lưu
        $results = [];

        return new WP_REST_Response([
            'success' => true,
            'keyword' => $keyword,
            'clinics' => $results,
        ], 200);
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

    public function get_clinics($request)
    {
        // TODO
    }
}
