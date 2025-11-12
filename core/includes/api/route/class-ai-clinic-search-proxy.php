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
            'methods' => 'GET',
            'callback' => [$this, 'get_districts'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/specialties', [
            'methods' => 'GET',
            'callback' => [$this, 'get_specialties'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/clinics/search', [
            'methods' => 'GET',
            'callback' => [$this, 'search_clinics'],
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
     * @param string $endpoint
     * @param array $params
     * @param array $headers
     * @return array|WP_Error
     */
    private function proxy_get($endpoint, $params = [], $headers = [])
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

        $api_base = Ai_Clinic_REST_Register::get_base(Ai_Clinic_REST_Register::GROUP_PUBLIC);
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
     * @param string $method
     * @param string $endpoint
     * @param array $body
     * @param array $headers
     * @return array|WP_Error
     */
    private function proxy_request($method, $endpoint, $body = [], $headers = [])
    {
        $rate_check = $this->check_rate_limit($endpoint);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        $api_base = Ai_Clinic_REST_Register::get_base(Ai_Clinic_REST_Register::GROUP_PUBLIC);
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
        $result = $this->proxy_get('/provinces', ['search' => $search]);

        return rest_ensure_response($result);
    }

    /**
     * Get districts list
     */
    public function get_districts(WP_REST_Request $request)
    {
        $province_code = $request->get_param('province_code');
        $search = $request->get_param('search');

        $params = [];
        if ($province_code) $params['province_code'] = $province_code;
        if ($search) $params['search'] = $search;

        $result = $this->proxy_get('/districts', $params);

        return rest_ensure_response($result);
    }

    /**
     * Get specialties list
     */
    public function get_specialties(WP_REST_Request $request)
    {
        $search = $request->get_param('search');
        $result = $this->proxy_get('/specialties', ['search' => $search]);

        return rest_ensure_response($result);
    }

    /**
     * Search clinics
     */
    public function search_clinics(WP_REST_Request $request)
    {
        $body = $request->get_json_params();

        if (empty($body)) {
            return new WP_Error('invalid_request', 'Request body is required', ['status' => 400]);
        }

        if (empty($body['query']) && empty($body['filters'])) {
            return new WP_Error(
                'invalid_request',
                'Either "query" or "filters" must be provided',
                ['status' => 400]
            );
        }

        if (isset($body['query'])) {
            $body['query'] = sanitize_text_field($body['query']);
        }

        if (isset($body['filters']) && is_array($body['filters'])) {
            $body['filters'] = $this->sanitize_filters($body['filters']);
        }

        if (isset($body['page'])) {
            $body['page'] = max(1, intval($body['page']));
        }
        if (isset($body['per_page'])) {
            $body['per_page'] = max(1, min(100, intval($body['per_page'])));
        }

        $headers = [
            'Content-Type' => $request->get_header('Content-Type') ?: 'application/json',
        ];

        $result = $this->proxy_request('POST', '/clinics/search', $body, $headers);

        return rest_ensure_response($result);
    }

    /**
     * Sanitize search filters
     */
    private function sanitize_filters($filters)
    {
        $sanitized = [];

        if (isset($filters['province_code'])) {
            $sanitized['province_code'] = sanitize_text_field($filters['province_code']);
        }

        if (isset($filters['district_code'])) {
            $sanitized['district_code'] = sanitize_text_field($filters['district_code']);
        }

        if (isset($filters['specialty_ids']) && is_array($filters['specialty_ids'])) {
            $sanitized['specialty_ids'] = array_map('intval', $filters['specialty_ids']);
        }

        return $sanitized;
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
}
