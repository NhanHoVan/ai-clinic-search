<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Service_Google_Places
 *
 * Google Places API v1 (searchText + place details).
 *
 * @package AICLINIC
 */
class Ai_Clinic_Service_Google_Places
{
    /**
     * @var Ai_Clinic_Search_DB
     */
    private $db;

    /**
     * @var string
     */
    private $api_key;

    /**
     * Base URL for Places API v1
     * @var string
     */
    private $api_url;

    public function __construct()
    {
        $this->db = new Ai_Clinic_Search_DB();
    }

    /**
     * Load API settings
     *
     * @throws Exception
     */
    private function loadSettings()
    {
        $settings = $this->db->aiclinic_get_settings([
            'google_places_api_key',
        ]);

        if (empty($settings['google_places_api_key'])) {
            throw new Exception('Google Places API key not configured');
        }

        $this->api_key = $settings['google_places_api_key'];
        $this->api_url = AICLINIC_GOOGLE_PLACES_API_URL;
    }


    /**
     * Text search using Places API v1
     *
     * @param string $query
     * @return array
     * @throws Exception
     */
    public function textSearch($query)
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/places:searchText';

        $body = [
            'textQuery' => $query,
            'languageCode' => 'vi',
            'regionCode' => 'VN',
            'pageSize'  => 10
        ];

        $headers = [
            'Content-Type'     => 'application/json',
            'X-Goog-Api-Key'   => $this->api_key,
            'X-Goog-FieldMask' => 'places.id,places.displayName.text,places.formattedAddress'
        ];

        return $this->post($endpoint, $body, $headers);
    }


    /**
     * Get place details v1 (phone, website, hours…)
     *
     * @param string $place_id
     * @return array
     * @throws Exception
     */
    public function placeDetails($place_id)
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/places/' . $place_id;

        $headers = [
            'X-Goog-Api-Key'   => $this->api_key,
            'X-Goog-FieldMask' =>
            'internationalPhoneNumber,websiteUri,location,rating,userRatingCount,editorialSummary.text'
        ];

        return $this->get($endpoint, $headers);
    }


    /**
     * POST request (for searchText)
     */
    private function post($url, $body, $headers)
    {
        $args = [
            'timeout' => 15,
            'headers' => $headers,
            'body'    => json_encode($body),
        ];

        $response = wp_remote_post($url, $args);

        return $this->handleResponse($response);
    }


    /**
     * GET request (place details)
     */
    private function get($url, $headers)
    {
        $args = [
            'timeout' => 15,
            'headers' => $headers,
        ];

        $response = wp_remote_get($url, $args);

        return $this->handleResponse($response);
    }


    /**
     * Handle & format response
     */
    private function handleResponse($response)
    {
        if (is_wp_error($response)) {
            throw new Exception('Google Places API request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if ($code !== 200) {
            $err = $json['error']['message'] ?? 'Unknown error';
            throw new Exception('Google Places API error: ' . $err);
        }

        return $this->formatResponse($json);
    }


    /**
     * Format searchText + placeDetails response
     */
    private function formatResponse(array $data)
    {
        // searchText returns: { places: [...] }
        if (isset($data['places'])) {
            $results = [];
            foreach ($data['places'] as $place) {
                $results[] = $this->formatPlace($place);
            }
            return [
                'status'  => 'OK',
                'results' => $results
            ];
        }

        // place details returns single place object
        if (isset($data['id'])) {
            return [
                'status' => 'OK',
                'result' => $this->formatPlace($data)
            ];
        }

        return [
            'status' => 'ZERO_RESULTS',
            'results' => []
        ];
    }


    /**
     * Convert Places v1 structure → your unified structure
     */
    private function formatPlace(array $place)
    {
        return [
            'place_id' => $place['id'] ?? '',
            'name'     => $place['displayName']['text'] ?? '',
            'address'  => $place['formattedAddress'] ?? '',
            'phone'    => $place['internationalPhoneNumber'] ?? null,
            'website'  => $place['websiteUri'] ?? null,
            'rating'   => $place['rating'] ?? null,
            'user_rating_count' => $place['userRatingCount'] ?? 0,
            'lat' => $place['location']['latitude'] ?? null,
            'lng' => $place['location']['longitude'] ?? null,
            'description' => $place['editorialSummary']['text'] ?? null,
        ];
    }
}
