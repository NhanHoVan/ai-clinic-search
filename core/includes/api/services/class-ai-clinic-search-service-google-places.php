<?php

/**
 * Class Ai_Clinic_Service_Google_Places
 *
 * Handles Google Places API requests for location-based queries.
 *
 * @package AICLINIC
 */
class Ai_Clinic_Service_Google_Places
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
     * @var string Google Places API base URL
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
            'google_places_api_key',
            'google_places_api_url'
        ]);

        if (empty($settings['google_places_api_key'])) {
            throw new Exception('Google Places API key not configured');
        }

        $this->api_key = $settings['google_places_api_key'];
        $this->api_url = !empty($settings['google_places_api_url'])
            ? $settings['google_places_api_url']
            : 'https://maps.googleapis.com/maps/api/place';
    }

    /**
     * Search places by text query
     *
     * @param string $query Search query
     * @param array $params Additional parameters
     * @return array Search results
     * @throws Exception If request fails
     */
    public function textSearch($query, array $params = [])
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/textsearch/json';

        $query_params = array_merge([
            'key' => $this->api_key,
            'query' => $query
        ], $params);

        return $this->request($endpoint, $query_params);
    }

    /**
     * Search nearby places
     *
     * @param float $latitude Latitude coordinate
     * @param float $longitude Longitude coordinate
     * @param int $radius Search radius in meters
     * @param array $params Additional parameters
     * @return array Search results
     * @throws Exception If request fails
     */
    public function nearbySearch($latitude, $longitude, $radius = 1500, array $params = [])
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/nearbysearch/json';

        $query_params = array_merge([
            'key' => $this->api_key,
            'location' => $latitude . ',' . $longitude,
            'radius' => $radius
        ], $params);

        return $this->request($endpoint, $query_params);
    }

    /**
     * Get place details
     *
     * @param string $place_id Google Place ID
     * @param array $fields Optional fields to retrieve
     * @return array Place details
     * @throws Exception If request fails
     */
    public function placeDetails($place_id, array $fields = [])
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/details/json';

        $default_fields = ['name', 'formatted_address', 'rating', 'geometry'];
        $fields_param = !empty($fields) ? implode(',', $fields) : implode(',', $default_fields);

        $query_params = [
            'key' => $this->api_key,
            'place_id' => $place_id,
            'fields' => $fields_param
        ];

        return $this->request($endpoint, $query_params);
    }

    /**
     * Send HTTP request to Google Places API
     *
     * @param string $endpoint API endpoint URL
     * @param array $params Query parameters
     * @return array Response data
     * @throws Exception If request fails
     */
    private function request($endpoint, array $params)
    {
        $url = $endpoint . '?' . http_build_query($params);

        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/json'
            ]
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            throw new Exception('Google Places API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200 || $data['status'] !== 'OK') {
            $error_msg = $data['error_message'] ?? $data['status'] ?? 'Unknown error';
            throw new Exception('Google Places API error: ' . $error_msg);
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
            'status' => $data['status'],
            'results' => []
        ];

        // Handle single result (place details)
        if (isset($data['result'])) {
            $formatted['result'] = $this->formatPlace($data['result']);
            return $formatted;
        }

        // Handle multiple results (search)
        if (!empty($data['results'])) {
            foreach ($data['results'] as $place) {
                $formatted['results'][] = $this->formatPlace($place);
            }
        }

        return $formatted;
    }

    /**
     * Format single place data
     *
     * @param array $place Raw place data
     * @return array Formatted place data
     */
    private function formatPlace(array $place)
    {
        return [
            'place_id' => $place['place_id'] ?? '',
            'name' => $place['name'] ?? '',
            'address' => $place['formatted_address'] ?? $place['vicinity'] ?? '',
            'rating' => $place['rating'] ?? null,
            'user_ratings_total' => $place['user_ratings_total'] ?? 0,
            'location' => [
                'lat' => $place['geometry']['location']['lat'] ?? null,
                'lng' => $place['geometry']['location']['lng'] ?? null
            ],
            'types' => $place['types'] ?? []
        ];
    }
}
