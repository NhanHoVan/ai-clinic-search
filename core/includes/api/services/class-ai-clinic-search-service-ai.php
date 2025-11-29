<?php

/**
 * Class Ai_Clinic_Service_OpenAI
 *
 * Handles communication with OpenAI API endpoints.
 *
 * @package AICLINIC
 */
class Ai_Clinic_Service_OpenAI
{
    /**
     * @var Ai_Clinic_Search_DB Database instance
     */
    private $db;

    /**
     * @var string OpenAI API key
     */
    private $api_key;

    /**
     * @var string OpenAI API base URL
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
            'openai_api_key',
            'openai_api_url'
        ]);

        // if (empty($settings['openai_api_key'])) {
        //     throw new Exception('OpenAI API key not configured');
        // }

        $this->api_key = !empty($settings['openai_api_key'])
            ? $settings['openai_api_key'] : AICLINIC_OPENAI_KEY;
        $this->api_url = !empty($settings['openai_api_url'])
            ? $settings['openai_api_url'] : AICLINIC_OPENAI_URL;
    }

    /**
     * Send chat completion request
     *
     * @param array $messages Array of message objects
     * @param array $options Additional options (model, temperature, etc.)
     * @return array Response data
     * @throws Exception If request fails
     */
    public function chatCompletion(array $messages, array $options = [])
    {
        $this->loadSettings();

        $endpoint = $this->api_url . '/chat/completions';

        $body = array_merge([
            'model' => $options['model'] ?? 'gpt-3.5-turbo',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1000
        ], $options);

        return $this->request('POST', $endpoint, $body);
    }

    /**
     * Send chat completion with structured output
     *
     * @param array $messages Array of message objects
     * @param string $schema_name Schema file name (without .json extension)
     * @param array $options Additional options (model, temperature, etc.)
     * @return array Response data
     * @throws Exception If request fails or schema not found
     */
    public function chatCompletionStructured(array $messages, $schema_name, array $options = [])
    {
        $this->loadSettings();

        $schema_file = CLINIC_SEARCH_PLUGIN_DIR . 'schemas/' . $schema_name . '.json';

        if (!file_exists($schema_file)) {
            throw new Exception('Schema file not found: ' . $schema_name);
        }

        $json_schema = json_decode(file_get_contents($schema_file), true);

        if (!$json_schema) {
            throw new Exception('Invalid JSON schema file: ' . $schema_name);
        }

        $endpoint = $this->api_url . '/chat/completions';

        $body = array_merge([
            'model' => $options['model'] ?? 'gpt-4o-mini',
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.3,
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => $json_schema
            ]
        ], $options);

        return $this->request('POST', $endpoint, $body);
    }

    /**
     * Send HTTP request to OpenAI API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint URL
     * @param array $body Request body
     * @return array Response data
     * @throws Exception If request fails
     */
    private function request($method, $endpoint, $body = [])
    {
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 30
        ];

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            throw new Exception('OpenAI API request failed: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $error_msg = $data['error']['message'] ?? 'Unknown error';
            throw new Exception('OpenAI API error: ' . $error_msg);
        }

        return $data;
    }

    /**
     * Build messages array for OpenAI structured clinic extraction
     *
     * @param array $search_results Raw data from Google Search
     * @return array $messages
     */
    private function buildClinicExtractionMessages(array $search_results): array
    {
        $system_prompt = <<<EOT
            You are a data extraction assistant.
            Extract clinic information from the input data.

            Rules:
            1. If a field is missing, output an empty string.
            2. Do NOT add extra tags or attributes.
            3. Output JSON matching the schema provided (clinics array with name, address, phone, email, description).
            4. No explanation or additional text.
        EOT;

        $user_prompt = json_encode($search_results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt],
        ];
    }

    /**
     * Extract clinic information from Google Search results using OpenAI structured output.
     *
     * @param array $search_results Raw data from Google Search, typically an array containing clinics.
     * 
     * @return array Returns an associative array with a 'results' key:
     *               ['clinics' => [
     *                           [
     *                               'name' => string,
     *                               'address' => string,
     *                               'phone' => string,
     *                               'email' => string,
     *                               'description' => string
     *                           ],
     *                           ...
     *                       ]
     *               ]
     * 
     * @throws Exception If the OpenAI API request fails or returns invalid/malformed data.
     */
    public function extractionClinics(array $search_results): array
    {
        $prompt_message = $this->buildClinicExtractionMessages($search_results);

        $data = $this->chatCompletionStructured(
            $prompt_message,
            "openai-clinic-extraction-schema"
        );

        if (!isset($data['results']['clinics']) || !is_array($data['results']['clinics'])) {
            throw new Exception('Invalid response from OpenAI: missing clinics array');
        }

        $normalized = array_map(function ($clinic) {
            return [
                'name' => $clinic['name'] ?? '',
                'address' => $clinic['address'] ?? '',
                'phone' => $clinic['phone'] ?? '',
                'email' => $clinic['email'] ?? '',
                'description' => $clinic['description'] ?? ''
            ];
        }, $data['results']['clinics']);

        return ['clinics' => $normalized];
    }
}
