<?php

/**
 * Class Ai_Clinic_Service_Clinic
 *
 * Handles logic for clinic service
 * 
 * @package AICLINIC
 */
class Ai_Clinic_Service_Clinic
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
     * Search clinics by keyword and location, extract clinic data using AI,
     * and insert or update clinics in the database.
     *
     * @param string $search        The search keyword (e.g. "dental clinic").
     * @param string $province_code The province code.
     * @param string $district_code The district code.
     *
     * @return array{
     *     success: bool,
     *     keyword: string,
     *     total_found?: int,
     *     message?: string,
     *     clinics: array
     * }
     */
    public function searchAndUpsertClinics(
        string $search,
        string $province_code,
        string $district_code
    ): array {
        $search_query = $this->buildSearchQuery(
            $search,
            $province_code,
            $district_code
        );

        $search_results = $this->google_search->search($search_query);

        if (empty($search_results['clinics'])) {
            return [
                'success' => false,
                'message' => 'No results found from Search',
                'keyword' => $search,
                'clinics' => [],
            ];
        }

        $clinics = $this->extractClinicsFromSearch(
            $search_results['clinics'],
            $province_code,
            $district_code
        );

        return [
            'success'     => true,
            'keyword'     => $search,
            'total_found' => count($clinics),
            'clinics'     => $clinics,
        ];
    }

    /**
     * Build a human-readable search query string for external search engines.
     *
     * Example output:
     * "Dental clinic tại Quận 1, Hồ Chí Minh"
     *
     * @param string $search        The search keyword.
     * @param string $province_code The province code.
     * @param string $district_code The district code.
     *
     * @return string The constructed search query.
     */
    private function buildSearchQuery(
        string $search,
        string $province_code,
        string $district_code
    ): string {
        $province_name = $this->location_service->get_province_name_by_code($province_code);

        $district_name = $this->location_service->get_district_name_by_code($province_code, $district_code);

        return trim(sprintf(
            '%s tại %s, %s',
            $search,
            $district_name,
            $province_name
        ));
    }

    /**
     * Extract structured clinic data from raw search results
     * and upsert them into the database.
     *
     * This method uses AI to parse unstructured search data
     * and persist valid clinic records.
     *
     * @param array  $raw_clinics   Raw clinic data returned from search engine.
     * @param string $province_code The province code.
     * @param string $district_code The district code.
     *
     * @return array A list of saved clinic records.
     */
    private function extractClinicsFromSearch(
        array $raw_clinics,
        string $province_code,
        string $district_code
    ): array {
        $data = $this->openai->extractionClinics($raw_clinics);

        $saved_clinics = [];
        foreach ($data['clinics'] as $clinic) {
            if (empty($clinic['name'])) {
                continue;
            }

            $saved = $this->upsertClinic(
                $clinic,
                $province_code,
                $district_code
            );

            if ($saved) {
                $saved_clinics[] = $saved;
            }
        }

        return $saved_clinics;
    }

    /**
     * Insert a new clinic or return the existing one if it already exists.
     *
     * A clinic is considered existing based on predefined uniqueness rules
     * (e.g. place_id, name + address, etc.).
     *
     * @param array  $clinic        The extracted clinic data.
     * @param string $province_code The province code.
     * @param string $district_code The district code.
     *
     * @return array|null The saved clinic record, or null on failure.
     */
    private function upsertClinic(
        array $clinic,
        string $province_code,
        string $district_code
    ): ?array {
        $clinic_data = [
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
            'is_active'     => 'ACTIVE',
        ];

        $existing = $this->checkClinicExists($clinic_data);
        if ($existing) {
            return $existing;
        }

        $clinic_id = $this->database->aiclinic_insert_clinic($clinic_data);
        if (!$clinic_id) {
            return null;
        }

        $result = $this->database->aiclinic_get_clinics([
            'filters' => ['id' => $clinic_id],
        ]);

        return $result[0] ?? null;
    }

    /**
     * Check whether a clinic already exists in the database.
     *
     * The existence check may be based on place_id,
     * name + address, or other business rules.
     *
     * @param array $clinic_data The clinic data to check.
     *
     * @return array|null Existing clinic record if found, otherwise null.
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
     * Check whether a clinic exists by ID.
     *
     * @param int $clinic_id
     * @return bool True if clinic exists, false otherwise.
     */
    public function clinic_exists(int $clinic_id): bool
    {
        if ($clinic_id <= 0) {
            return false;
        }

        $clinic = $this->database->aiclinic_get_clinic_by_id($clinic_id);

        return !empty($clinic);
    }

    /**
     * Filter and mask clinic data for API response
     *
     * @param array $clinics List of clinics
     * @return array Filtered and masked clinic data
     */
    public function filter_and_mask_clinics(array $clinics): array
    {
        return array_map(function ($clinic) {
            return [
                'id'          => $clinic['id'] ?? '',
                'name'        => $clinic['name'] ?? '',
                'description' => $clinic['description'] ?? '',
                'image'       => $clinic['image'] ?? '',
                'address'     => $clinic['address'] ?? '',
                'province'    => $clinic['province'] ?? '',
                'district'    => $clinic['district'] ?? '',
                'phone'       => $this->maskPhone($clinic['phone'] ?? ''),
                'email'       => $this->maskEmail($clinic['email'] ?? ''),
                'website'     => $clinic['website'] ?? '',
                'verify_status' => $clinic['verify_status'] ?? '',
                'is_active'     => $clinic['is_active'] ?? '',
                'created_at'     => $clinic['created_at'] ?? '',
            ];
        }, $clinics);
    }

    /**
     * Mask phone number
     *
     * @param string $phone
     * @return string
     */
    private function maskPhone(string $phone): string
    {
        if ($phone === "") return "";
        $s = (string) $phone;
        if (strlen($s) <= 7) return $s;
        $first3 = substr($s, 0, 3);
        $last4  = substr($s, -4);
        return "{$first3} *** {$last4}";
    }

    /**
     * Mask email
     *
     * @param string $email
     * @return string
     */
    private function maskEmail(string $email): string
    {
        if (!$email) return "";
        return preg_replace("/^(.{2}).+(@.+)$/", "$1***$2", $email);
    }

    /**
     * Sync clinic specialties (replace all if provided).
     *
     * @param int        $clinic_id
     * @param array|null $specialties
     *   - null  => do nothing
     *   - []    => remove all tags
     *   - [...] => replace tags
     *
     * @return true|WP_Error
     */
    public function sync_clinic_specialties($clinic_id, ?array $specialties)
    {
        if ($specialties === null) {
            return true;
        }

        if (!is_array($specialties)) {
            return new WP_Error(
                'invalid_specialties',
                'specialties must be an array of tag names',
                ['status' => 400]
            );
        }

        $tag_names = array_values(array_unique(array_filter(array_map(
            static function ($name) {
                return is_string($name) ? trim($name) : '';
            },
            $specialties
        ))));

        $result = $this->database->aiclinic_sync_clinic_tags_by_name(
            absint($clinic_id),
            $tag_names
        );

        if ($result === false) {
            return new WP_Error(
                'tag_sync_failed',
                'Failed to sync clinic specialties',
                ['status' => 500]
            );
        }

        return true;
    }
}
