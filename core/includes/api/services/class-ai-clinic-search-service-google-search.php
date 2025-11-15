<?php

/**
 * Class Ai_Clinic_Service_Google_Search
 *
 * Handles Google Custom Search API requests.
 *
 * @package AICLINIC
 */
class Ai_Clinic_Service_Google_Search
{
    /**
     * @var Ai_Clinic_Search_DB Database instance
     */
    private $db;

    /**
     * @var string Google API key
     */
    private $api_key;

    /**
     * @var string Custom Search Engine ID
     */
    private $cx;

    /**
     * @var string Google Search API base URL
     */
    private $api_url;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new Ai_Clinic_Search_DB();
    }

    /**
     * Load API settings from database
     *
     * @throws Exception If settings cannot be loaded
     * @return void
     */
    private function loadSettings()
    {
        $settings = $this->db->aiclinic_get_settings([
            'google_search_api_key',
            'google_search_cx',
            'google_search_api_url'
        ]);

        if (empty($settings['google_search_api_key'])) {
            throw new Exception('Google Search API key not configured');
        }

        if (empty($settings['google_search_cx'])) {
            throw new Exception('Google Search CX not configured');
        }

        $this->api_key = $settings['google_search_api_key'];
        $this->cx = $settings['google_search_cx'];
        $this->api_url = !empty($settings['google_search_api_url'])
            ? $settings['google_search_api_url']
            : 'https://www.googleapis.com/customsearch/v1';
    }

    /**
     * Perform search query
     *
     * @param string $query Search query string
     * @param array $params Additional search parameters
     * @return array Search results
     * @throws Exception If request fails
     */
    public function search($query, array $params = [])
    {
        $this->loadSettings();

        $default_params = [
            'key' => $this->api_key,
            'cx' => $this->cx,
            'q' => $query,
            'num' => $params['num'] ?? 10,
            'start' => $params['start'] ?? 1
        ];

        $query_params = array_merge($default_params, $params);
        $url = $this->api_url . '?' . http_build_query($query_params);

        return $this->request($url);
    }

    /**
     * Send HTTP request to Google Search API
     *
     * @param string $url Full request URL with parameters
     * @return array Response data
     * @throws Exception If request fails
     */
    private function request($url)
    {
        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('Google Search API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_msg = $data['error']['message'] ?? 'Unknown error';
            throw new Exception('Google Search API error: ' . $error_msg);
        }

        return $this->formatResponse($data);
    }

    /**
     * Format response according to schema
     *
     * @param array $data Raw API response
     * @return array Formatted response
     */
    private function formatResponse(array $data)
    {
        $formatted = [
            'total_results' => $data['searchInformation']['totalResults'] ?? 0,
            'search_time' => $data['searchInformation']['searchTime'] ?? 0,
            'items' => []
        ];

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $formatted['items'][] = [
                    'title' => $item['title'] ?? '',
                    'link' => $item['link'] ?? '',
                    'snippet' => $item['snippet'] ?? '',
                    'display_link' => $item['displayLink'] ?? ''
                ];
            }
        }

        return $formatted;
    }
}
