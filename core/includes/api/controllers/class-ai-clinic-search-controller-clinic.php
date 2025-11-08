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
    public function get_clinics($request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiclinic_clinics';
        $rows = $wpdb->get_results("SELECT id, name, address, province, district FROM $table");

        return $rows ?: [];
    }

    public function create_clinic($request)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiclinic_clinics';

        $params = $request->get_json_params();
        $name = sanitize_text_field($params['name'] ?? '');
        $address = sanitize_textarea_field($params['address'] ?? '');
        $province = sanitize_text_field($params['province'] ?? '');
        $district = sanitize_text_field($params['district'] ?? '');

        if (!$name) {
            return new WP_Error('missing_name', 'Tên phòng khám là bắt buộc.', ['status' => 400]);
        }

        $wpdb->insert($table, compact('name', 'address', 'province', 'district'));

        return rest_ensure_response([
            'id' => $wpdb->insert_id,
            'message' => 'Đã tạo phòng khám mới',
        ]);
    }
}
