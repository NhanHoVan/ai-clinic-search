<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Proxy_Controller
 *
 * Proxy layer to internally call other REST APIs (e.g. AI Clinic Search), 
 * helps hide API keys and handle intermediate requests.
 * 
 * @package     AICLINIC
 * @subpackage  Classes/Ai_Clinic_Proxy_Controller
 * @author      Van Nhan
 * @since       1.0.0
 */
class Ai_Clinic_Proxy_Controller
{
    const CACHE_TTL = 300;
    const RATE_LIMIT_MAX = 20;
    const RATE_LIMIT_WINDOW = 60;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register proxy routes
     */
    public function register_routes()
    {
        $namespace = Ai_Clinic_REST_Register::get_base(Ai_Clinic_REST_Register::GROUP_PROXY);

        register_rest_route($namespace, '/provinces', [
            'methods' => 'GET',
            'callback' => [$this, 'get_provinces'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/districts', [
            'methods' => 'POST',
            'callback' => [$this, 'get_districts'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/specialties', [
            'methods' => 'GET',
            'callback' => [$this, 'get_specialties'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/clinics/search', [
            'methods' => 'POST',
            'callback' => [$this, 'search_clinics'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/clinics', [
            'methods' => 'POST',
            'callback' => [$this, 'get_refer_clinics'],
            'permission_callback' => '__return_true',
        ]);

        // Admin route to clear cache
        register_rest_route($namespace, '/cache/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_cache'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        // TODO check
        register_rest_route($namespace, '/clinic', [
            'methods' => 'GET',
            'callback' => [$this, 'get_clinic'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($namespace, '/clinic/suggest-update', [
            'methods' => 'POST',
            'callback' => [$this, 'suggest_update_clinic'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($namespace, '/clinic/suggest-create', [
            'methods' => 'POST',
            'callback' => [$this, 'suggest_create_clinic'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    /**
     * Get API key from wp-config or options
     */
    private function get_api_key()
    {
        if (defined('AI_CLINIC_SEARCH_INTERNAL_API_KEY')) {
            return AI_CLINIC_SEARCH_INTERNAL_API_KEY;
        }
        return get_option('ai_clinic_search_internal_api_key', '');
    }

    /**
     * Check rate limiting for endpoint
     * 
     * @param string $endpoint
     * @return bool|WP_Error
     */
    private function check_rate_limit($endpoint)
    {
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        $identifier = $user_id ?: $ip;

        $key = 'aiclinic_rate_limit_' . md5($endpoint . $identifier);
        $count = get_transient($key);

        if ($count && $count >= self::RATE_LIMIT_MAX) {
            error_log("[AiClinicProxyController] Rate limit exceeded for $identifier on $endpoint");
            return new WP_Error('rate_limit', 'Too many requests. Please try again later.', [
                'status' => 429,
                'limit' => self::RATE_LIMIT_MAX,
                'window' => self::RATE_LIMIT_WINDOW,
            ]);
        }

        set_transient($key, ($count ?: 0) + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Proxy GET request with cache support
     * 
     * @param string $base
     * @param string $endpoint
     * @param array $params
     * @param array $headers
     * @return array|WP_Error
     */
    private function proxy_get($base, $endpoint, $params = [], $headers = [])
    {
        $rate_check = $this->check_rate_limit($endpoint);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        $cache_key = 'aiclinic_proxy_' . md5($endpoint . serialize($params));
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $api_base = Ai_Clinic_REST_Register::get_base($base);
        $url = rest_url(ltrim($api_base . $endpoint, '/'));

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $default_headers = [
            'X-APP-KEY' => $this->get_api_key(),
        ];
        $headers = array_merge($default_headers, $headers);

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            error_log('[AiClinicProxyController] GET error: ' . $response->get_error_message());
            return new WP_Error('proxy_error', $response->get_error_message(), ['status' => 500]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            error_log("[AiClinicProxyController] HTTP $status_code from $url: $body");
            return new WP_Error('api_error', "API returned error (HTTP $status_code)", [
                'status' => $status_code,
                'response' => $body,
            ]);
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[AiClinicProxyController] Invalid JSON from ' . $url . ': ' . json_last_error_msg());
            return new WP_Error('invalid_json', 'Invalid response from API', ['status' => 500]);
        }

        if ($status_code === 200) {
            set_transient($cache_key, $data, self::CACHE_TTL);
        }

        return $data;
    }

    /**
     * Proxy POST/PUT/DELETE request
     * 
     * @param string $base
     * @param string $method
     * @param string $endpoint
     * @param array $body
     * @param array $headers
     * @return array|WP_Error
     */
    private function proxy_request($base, $method, $endpoint, $body = [], $headers = [])
    {
        $rate_check = $this->check_rate_limit($endpoint);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        $api_base = Ai_Clinic_REST_Register::get_base($base);
        $url = rest_url(ltrim($api_base . $endpoint, '/'));

        $default_headers = [
            'X-APP-KEY' => $this->get_api_key(),
        ];
        $headers = array_merge($default_headers, $headers);

        $content_type = isset($headers['Content-Type']) ? $headers['Content-Type'] : 'application/json';

        $options = [
            'method'  => strtoupper($method),
            'headers' => $headers,
            'timeout' => 20,
            'sslverify' => true,
        ];

        if (!empty($body)) {
            if (stripos($content_type, 'application/json') !== false) {
                $options['body'] = wp_json_encode($body);
            } else {
                $options['body'] = $body;
            }
        }

        $response = wp_remote_request($url, $options);

        if (is_wp_error($response)) {
            error_log('[AiClinicProxyController] ' . $method . ' error: ' . $response->get_error_message());
            return new WP_Error('proxy_error', $response->get_error_message(), ['status' => 500]);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_response = wp_remote_retrieve_body($response);

        if ($status_code >= 400) {
            error_log("[AiClinicProxyController] $method HTTP $status_code from $url: $body_response");
            return new WP_Error('api_error', "API returned error (HTTP $status_code)", [
                'status' => $status_code,
                'response' => $body_response,
            ]);
        }

        $data = json_decode($body_response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[AiClinicProxyController] Invalid JSON in ' . $method . ' response from ' . $url);
            return new WP_Error('invalid_json', 'Invalid response from API', ['status' => 500]);
        }

        return $data;
    }

    // ---------- ROUTE HANDLERS ---------- //

    /**
     * Get provinces list
     */
    public function get_provinces(WP_REST_Request $request)
    {
        $search = $request->get_param('search');
        $params = [];

        if (!empty($search)) {
            $params['search'] = sanitize_text_field($search);
        }

        $result = $this->proxy_get(
            Ai_Clinic_REST_Register::GROUP_PUBLIC,
            '/provinces',
            $params
        );

        return rest_ensure_response($result);
    }

    /**
     * Get districts list
     */
    public function get_districts(WP_REST_Request $request)
    {
        $body = $request->get_json_params();

        if (empty($body)) {
            return new WP_Error('invalid_request', 'Request body is required', ['status' => 400]);
        }

        if (empty($body['province_code'])) {
            return new WP_Error(
                'invalid_request',
                'Field "province_code" is required',
                ['status' => 400]
            );
        }

        $params = [
            'province_code' => sanitize_text_field($body['province_code'])
        ];

        if (isset($body['search']) && !empty($body['search'])) {
            $params['search'] = sanitize_text_field($body['search']);
        }

        $result = $this->proxy_get(
            Ai_Clinic_REST_Register::GROUP_PUBLIC,
            '/districts',
            $params
        );

        return rest_ensure_response($result);
    }

    /**
     * Get specialties list
     */
    public function get_specialties(WP_REST_Request $request)
    {
        $search = $request->get_param('search');
        $params = [];

        if (!empty($search)) {
            $params['search'] = sanitize_text_field($search);
        }

        $result = $this->proxy_get(
            Ai_Clinic_REST_Register::GROUP_PUBLIC,
            '/specialties',
            $params
        );

        return rest_ensure_response($result);
    }

    /**
     * Search clinics
     */
    public function search_clinics(WP_REST_Request $request)
    {
        $body_request = $request->get_json_params();

        if (empty($body_request)) {
            return new WP_Error('invalid_request', 'Request body is required', ['status' => 400]);
        }

        if (empty($body_request['search'])) {
            return new WP_Error(
                'invalid_request',
                'Field "search" is required',
                ['status' => 400]
            );
        }

        if (empty($body_request['province'])) {
            return new WP_Error(
                'invalid_request',
                'Field "province" is required',
                ['status' => 400]
            );
        }

        if (empty($body_request['district'])) {
            return new WP_Error(
                'invalid_request',
                'Field "district" is required',
                ['status' => 400]
            );
        }

        $body = [
            'search' => sanitize_text_field($body_request['search']),
            'province' => sanitize_text_field($body_request['province']),
            'district' => sanitize_text_field($body_request['district']),
        ];

        $headers = [
            'Content-Type' => $request->get_header('Content-Type') ?: 'application/json',
        ];

        $result = $this->proxy_request(
            Ai_Clinic_REST_Register::GROUP_PUBLIC,
            'POST',
            '/clinics/search',
            $body,
            $headers
        );

        return rest_ensure_response($result);
    }

    /**
     * Get refer clinics
     */
    public function get_refer_clinics(WP_REST_Request $request)
    {
        $body_request = $request->get_json_params();

        if (empty($body_request)) {
            return new WP_Error('invalid_request', 'Request body is required', ['status' => 400]);
        }

        if (empty($body_request['limit'])) {
            return new WP_Error(
                'invalid_request',
                'Field "limit" is required',
                ['status' => 400]
            );
        }

        if (empty($body_request['page'])) {
            return new WP_Error(
                'invalid_request',
                'Field "page" is required',
                ['status' => 400]
            );
        }

        $body = [
            'province' => sanitize_text_field($body_request['province']),
            'district' => sanitize_text_field($body_request['district']),
            'limit' => sanitize_text_field($body_request['limit']),
            'page' => sanitize_text_field($body_request['page']),
        ];

        $headers = [
            'Content-Type' => $request->get_header('Content-Type') ?: 'application/json',
        ];

        $result = $this->proxy_request(
            Ai_Clinic_REST_Register::GROUP_PUBLIC,
            'POST',
            '/clinics',
            $body,
            $headers
        );

        return rest_ensure_response($result);
    }

    /**
     * Clear proxy cache
     * Admin only endpoint
     */
    public function clear_cache(WP_REST_Request $request)
    {
        global $wpdb;

        $endpoint = $request->get_param('endpoint');

        if ($endpoint) {
            $pattern = '_transient_aiclinic_proxy_' . md5($endpoint . '%');
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $pattern
            ));
            $message = "Cache cleared for endpoint: $endpoint";
        } else {
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_aiclinic_proxy_%' 
                    OR option_name LIKE '_transient_timeout_aiclinic_proxy_%'"
            );
            $message = "All proxy cache cleared";
        }

        return rest_ensure_response([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * Get clinic detail
     */
    public function get_clinic(WP_REST_Request $request)
    {
        $id = $request->get_param('id');
        $params = [];

        if (!empty($id)) {
            $params['id'] = sanitize_text_field($id);
        }

        $result = $this->proxy_get(
            Ai_Clinic_REST_Register::GROUP_ADMIN,
            '/clinic',
            $params
        );

        return rest_ensure_response($result);
    }

    /**
     * Suggest update clinic detail
     */
    public function suggest_update_clinic(WP_REST_Request $request)
    {
        $body_request = $request->get_json_params();

        if (empty($body_request)) {
            return new WP_Error(
                'invalid_request',
                'Request body is required',
                ['status' => 400]
            );
        }

        if (empty($body_request['id'])) {
            return new WP_Error(
                'invalid_request',
                'Field "id" is required',
                ['status' => 400]
            );
        }

        $clinic_id = absint($body_request['id']);

        $suggested_by = (!empty($body_request['verifier']) && $body_request['verifier'] === 'owner')
            ? 'OWNER'
            : 'OTHER';

        $suggested_fields = $body_request;
        unset($suggested_fields['id'], $suggested_fields['verifier']);

        $body = [
            'suggestion_type'  => 'EDIT',
            'clinic_id'        => $clinic_id,
            'suggested_by'     => $suggested_by,
            'suggested_fields' => $suggested_fields,
        ];

        $headers = ['Content-Type' => 'application/json'];

        $result = $this->proxy_request(
            Ai_Clinic_REST_Register::GROUP_ADMIN,
            'POST',
            '/clinic/suggestions',
            $body,
            $headers
        );

        return rest_ensure_response($result);
    }

    /**
     * Suggest create clinic detail
     */
    public function suggest_create_clinic(WP_REST_Request $request)
    {
        $body_request = $request->get_json_params();

        if (empty($body_request)) {
            return new WP_Error(
                'invalid_request',
                'Request body is required',
                ['status' => 400]
            );
        }

        if (empty($body_request['name'])) {
            return new WP_Error(
                'invalid_request',
                'Field "name" is required',
                ['status' => 400]
            );
        }

        if (empty($body_request['address'])) {
            return new WP_Error(
                'invalid_request',
                'Field "address" is required',
                ['status' => 400]
            );
        }

        $suggested_by = (!empty($body_request['verifier']) && $body_request['verifier'] === 'owner')
            ? 'OWNER'
            : 'OTHER';

        $suggested_fields = $body_request;
        unset($suggested_fields['verifier']);

        $body = [
            'suggestion_type'  => 'CREATE',
            'suggested_by'     => $suggested_by,
            'suggested_fields' => $suggested_fields,
        ];

        $headers = [
            'Content-Type' => $request->get_header('Content-Type') ?: 'application/json',
        ];

        $result = $this->proxy_request(
            Ai_Clinic_REST_Register::GROUP_ADMIN,
            'POST',
            '/clinic/suggestions',
            $body,
            $headers
        );

        return rest_ensure_response($result);
    }
}
