<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Search_DB
 *
 * This class contains functions
 * used to interact with the DB in the plugin.
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_DB
 * @author		Van Nhan
 * @since		1.0.0
 */
class Ai_Clinic_Search_DB
{

    /**
     * wpdb
     *
     * @var		global
     * @since   1.0.0
     */
    private $wpdb;

    /**
     * helper
     *
     * @var		Ai_Clinic_Search_Helpers
     * @since   1.0.0
     */
    private $helper;

    /**
     * Construct
     */
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;

        $this->add_hooks();

        $this->helper = new Ai_Clinic_Search_Helpers();
    }

    /**
     * Registers related hooks
     *
     * @access	private
     * @since	1.0.0
     * @return	void
     */
    private function add_hooks()
    {
        // TODO
    }

    /**
     * ######################
     * ###
     * #### CALLABLE FUNCTIONS
     * ###
     * ######################
     */

    /**
     * Creates setting and customer tables.
     */
    public function aiclinic_create_table()
    {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_file = CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/init.sql';
        if (!file_exists($sql_file)) {
            error_log("Database file not found: " . $sql_file);
            return;
        }

        $sql_content = file_get_contents($sql_file);
        if (!$sql_content) {
            error_log("Failed to read database file.");
            return;
        }

        // Replace WordPress prefix and charset collate
        $sql_content = str_replace(
            ['wp_', '{CHARSET_COLLATE}'],
            [$this->wpdb->prefix, $charset_collate],
            $sql_content
        );

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $queries = explode(';', $sql_content);

        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                // Extract table name from the CREATE TABLE statement
                if (preg_match('/CREATE TABLE IF NOT EXISTS `?([^` ]+)`?/i', $query, $matches)) {
                    $table_name = $matches[1];

                    // Check if the table already exists
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                        // If the table does not exist, create a new one
                        dbDelta($query);
                    } else {
                        // If the table exists, check and update its schema
                        $this->helper->database->aiclinic_update_table_schema($table_name, $query);
                    }
                }
            }
        }
    }

    /**
     * ============================================================================
     * CLINICS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert a new clinic record.
     *
     * @param array $data {
     *     @type string $name           Clinic name (required)
     *     @type string $place_id      ID of places (required)
     *     @type string $address        Full address
     *     @type string $province       Province/city code
     *     @type string $district       District/area code
     *     @type float  $lat            Latitude
     *     @type float  $lng            Longitude
     *     @type string $phone          Contact phone
     *     @type string $email          Contact email
     *     @type string $website        Website
     *     @type string $description    Clinic description
     *     @type string $image          Clinic avatar image
     *     @type string $verify_status  Verification status (AI_DETECTED|ADMIN_VERIFIED|OWNER_VERIFIED)
     *     @type string $is_active      Display status (ACTIVE|HIDDEN)
     * }
     *
     * @return int|false The inserted clinic ID or false on failure.
     */
    public function aiclinic_insert_clinic($data)
    {
        $image = esc_url_raw($data['image'] ?? '');
        if ($image && !preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $image)) {
            $image = '';
        }

        $data_to_insert = [
            'name'          => sanitize_text_field($data['name']),
            'place_id'     => sanitize_text_field($data['place_id']),
            'address'       => sanitize_textarea_field($data['address'] ?? ''),
            'province'      => sanitize_text_field($data['province'] ?? ''),
            'district'      => sanitize_text_field($data['district'] ?? ''),
            'lat'           => !empty($data['lat']) ? (float) $data['lat'] : null,
            'lng'           => !empty($data['lng']) ? (float) $data['lng'] : null,
            'phone'         => sanitize_text_field($data['phone'] ?? ''),
            'email'         => sanitize_email($data['email'] ?? ''),
            'website'       => sanitize_url($data['website'] ?? ''),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
            'image'         => $image,
            'verify_status' => in_array($data['verify_status'] ?? '', ['AI_DETECTED', 'ADMIN_VERIFIED', 'OWNER_VERIFIED'])
                ? $data['verify_status']
                : 'AI_DETECTED',
            'is_active'     => in_array($data['is_active'] ?? '', ['ACTIVE', 'HIDDEN'])
                ? $data['is_active']
                : 'ACTIVE',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ];

        $result = $this->helper->database->aiclinic_insert('aiclinic_clinics', $data_to_insert);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get clinics with optional filtering
     *
     * @param array $args Filter arguments
     * @return array|false Array of clinics or false on error
     */
    public function aiclinic_get_clinics($args = [])
    {
        $defaults = [
            'search'        => '',
            'column_search' => 'name',
            'limit'         => 10,
            'offset'        => 0,
            'order_by'      => 'id',
            'sort_direction' => 'DESC',
            'filters'       => []
        ];

        $args = wp_parse_args($args, $defaults);

        // Build filters
        $filters = [];

        if (!empty($args['province'])) {
            $filters['province'] = sanitize_text_field($args['province']);
        }

        if (!empty($args['district'])) {
            $filters['district'] = sanitize_text_field($args['district']);
        }

        if (!empty($args['verify_status'])) {
            $filters['verify_status'] = sanitize_text_field($args['verify_status']);
        }

        if (!empty($args['is_active'])) {
            $filters['is_active'] = sanitize_text_field($args['is_active']);
        }

        $query_args = [
            'search'         => $args['search'],
            'column_search'  => $args['column_search'],
            'limit'          => $args['limit'],
            'offset'         => $args['offset'],
            'order_by'       => $args['order_by'],
            'sort_direction' => $args['sort_direction'],
            'filters'        => $filters,
        ];

        // Handle specialty_ids filter with JOIN
        if (!empty($args['specialty_ids']) && is_array($args['specialty_ids'])) {
            $specialty_ids = array_map('intval', $args['specialty_ids']);
            $specialty_ids = array_filter($specialty_ids);

            if (!empty($specialty_ids)) {
                $query_args['select'] = 'DISTINCT c.*';
                $query_args['joins'] = [
                    [
                        'type'  => 'INNER',
                        'table' => 'aiclinic_tag_relations tr',
                        'on'    => 'c.id = tr.clinic_id'
                    ]
                ];
                $query_args['filters']['tr.tag_id'] = $specialty_ids;
            }
        }

        return $this->helper->database->aiclinic_get_all('aiclinic_clinics c', $query_args);
    }

    /**
     * Get paginated clinics with total counts (for DataTables).
     *
     * @param array $args {
     *     @type string $search        Search keyword
     *     @type string $column_search Column to search in
     *     @type string $province      Province filter
     *     @type string $district      District filter
     *     @type array  $specialty_ids Array of tag IDs to filter by
     *     @type string $verify_status Verification status filter
     *     @type string $is_active     Active status filter
     *     @type int    $limit         Records per page
     *     @type int    $offset        Offset for pagination
     * }
     * @return array {
     *     @type array $data     Array of clinic records
     *     @type int   $total    Total records in table
     *     @type int   $filtered Total filtered records
     * }
     */
    public function aiclinic_get_clinics_paginated($args = [])
    {
        // Prepare filters for count
        $count_args = [
            'search'        => $args['search'] ?? '',
            'column_search' => $args['column_search'] ?? 'name',
            'filters'       => []
        ];

        if (!empty($args['province'])) {
            $count_args['filters']['province'] = sanitize_text_field($args['province']);
        }

        if (!empty($args['district'])) {
            $count_args['filters']['district'] = sanitize_text_field($args['district']);
        }

        if (!empty($args['verify_status'])) {
            $count_args['filters']['verify_status'] = sanitize_text_field($args['verify_status']);
        }

        if (!empty($args['is_active'])) {
            $count_args['filters']['is_active'] = sanitize_text_field($args['is_active']);
        }

        // Handle specialty_ids filter
        if (!empty($args['specialty_ids']) && is_array($args['specialty_ids'])) {
            $specialty_ids = array_map('intval', $args['specialty_ids']);
            $specialty_ids = array_filter($specialty_ids);

            if (!empty($specialty_ids)) {
                $count_args['joins'] = [
                    [
                        'type'  => 'INNER',
                        'table' => 'aiclinic_tag_relations tr',
                        'on'    => 'c.id = tr.clinic_id'
                    ]
                ];
                $count_args['filters']['tr.tag_id'] = $specialty_ids;
                $count_args['group_by'] = 'c.id';
                $count_args['distinct'] = 'c.id';
            }
        }

        return [
            'data'     => $this->aiclinic_get_clinics($args) ?: [],
            'total'    => $this->helper->database->aiclinic_count_record('aiclinic_clinics', []) ?: 0,
            'filtered' => $this->helper->database->aiclinic_count_record('aiclinic_clinics c', $count_args) ?: 0
        ];
    }

    /**
     * Get a single clinic by ID.
     *
     * @param int $clinic_id The clinic ID.
     * @return array|null The clinic record or null if not found.
     */
    public function aiclinic_get_clinic_by_id($clinic_id)
    {
        return $this->helper->database->aiclinic_get_by_id('aiclinic_clinics', absint($clinic_id));
    }

    /**
     * Update a clinic record.
     *
     * @param int   $clinic_id The clinic ID to update.
     * @param array $data      Data to update (same structure as insert).
     * @return int|false Number of rows updated or false on failure.
     */
    public function aiclinic_update_clinic($clinic_id, $data)
    {
        $data_to_update = [];

        if (isset($data['name'])) {
            $data_to_update['name'] = sanitize_text_field($data['name']);
        }

        if (isset($data['place_id'])) {
            $data_to_update['place_id'] = sanitize_text_field($data['place_id']);
        }

        if (isset($data['address'])) {
            $data_to_update['address'] = sanitize_textarea_field($data['address']);
        }

        if (isset($data['province'])) {
            $data_to_update['province'] = sanitize_text_field($data['province']);
        }

        if (isset($data['district'])) {
            $data_to_update['district'] = sanitize_text_field($data['district']);
        }

        if (isset($data['lat'])) {
            $data_to_update['lat'] = !empty($data['lat']) ? (float) $data['lat'] : null;
        }

        if (isset($data['lng'])) {
            $data_to_update['lng'] = !empty($data['lng']) ? (float) $data['lng'] : null;
        }

        if (isset($data['phone'])) {
            $data_to_update['phone'] = sanitize_text_field($data['phone']);
        }

        if (isset($data['email'])) {
            $data_to_update['email'] = sanitize_email($data['email']);
        }

        if (isset($data['website'])) {
            $data_to_update['website'] = sanitize_url($data['website']);
        }

        if (isset($data['description'])) {
            $data_to_update['description'] = sanitize_textarea_field($data['description']);
        }

        if (isset($data['image'])) {
            $image = esc_url_raw($data['image'] ?? '');
            if ($image && !preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $image)) {
                $image = '';
            }
            $data_to_update['image'] = $image;
        }

        if (isset($data['verify_status']) && in_array($data['verify_status'], ['AI_DETECTED', 'ADMIN_VERIFIED', 'OWNER_VERIFIED'])) {
            $data_to_update['verify_status'] = $data['verify_status'];
        }

        if (isset($data['is_active']) && in_array($data['is_active'], ['ACTIVE', 'HIDDEN'])) {
            $data_to_update['is_active'] = $data['is_active'];
        }

        $data_to_update['updated_at'] = current_time('mysql');

        return $this->helper->database->aiclinic_update(
            'aiclinic_clinics',
            $data_to_update,
            ['id' => absint($clinic_id)]
        );
    }

    /**
     * Delete a clinic by ID (cascades to reviews and tag relations).
     *
     * @param int $clinic_id The clinic ID to delete.
     * @return int|false Number of rows deleted or false on failure.
     */
    public function aiclinic_delete_clinic($clinic_id)
    {
        return $this->helper->database->aiclinic_delete_record_by_id('aiclinic_clinics', absint($clinic_id));
    }

    /**
     * Bulk delete clinics by IDs.
     *
     * @param array $clinic_ids Array of clinic IDs.
     * @return int Number of deleted clinics.
     */
    public function aiclinic_bulk_delete_clinics(array $clinic_ids): int
    {
        $deleted = 0;
        foreach ($clinic_ids as $id) {
            $result = $this->aiclinic_delete_clinic($id);
            if ($result !== false) {
                $deleted += $result;
            }
        }
        return $deleted;
    }

    /**
     * Count clinics by conditions.
     *
     * @param array $args {
     *     @type string $search        Search keyword
     *     @type string $column_search Column to search in
     *     @type string $province      Filter by province
     *     @type string $district      Filter by district
     *     @type string $verify_status Filter by verification status
     *     @type string $is_active     Filter by active status
     * }
     *
     * @return int|null Number of matching clinics.
     */
    public function aiclinic_count_clinics($args = [])
    {
        $count_args = [
            'search'        => $args['search'] ?? '',
            'column_search' => $args['column_search'] ?? 'name',
            'filters'       => []
        ];

        if (!empty($args['province'])) {
            $count_args['filters']['province'] = sanitize_text_field($args['province']);
        }

        if (!empty($args['district'])) {
            $count_args['filters']['district'] = sanitize_text_field($args['district']);
        }

        if (!empty($args['verify_status'])) {
            $count_args['filters']['verify_status'] = sanitize_text_field($args['verify_status']);
        }

        if (!empty($args['is_active'])) {
            $count_args['filters']['is_active'] = sanitize_text_field($args['is_active']);
        }

        return $this->helper->database->aiclinic_count_record('aiclinic_clinics', $count_args);
    }

    /**
     * Get clinics by place_id.
     *
     * @param string $place_id     ID of Places.
     * @param int    $limit        Number of records (default: 1).
     * @return array Array of clinic records.
     */
    public function aiclinic_get_clinics_by_place_id($place_id, $limit = 1)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_clinics', [
            'filters' => ['place_id' => sanitize_text_field($place_id)],
            'limit'   => absint($limit)
        ]) ?: [];
    }

    /**
     * Get clinics by phone.
     *
     * @param string $phone     phone of Clinic.
     * @param int    $limit     Number of records (default: 1).
     * @return array Array of clinic records.
     */
    public function aiclinic_get_clinics_by_phone($phone, $limit = 1)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_clinics', [
            'filters' => ['phone' => sanitize_text_field($phone)],
            'limit'   => absint($limit)
        ]) ?: [];
    }

    /**
     * Get clinics by name and province code.
     *
     * @param string $name           Name of the clinic to search for.
     * @param string|int $province_code  Province code to filter clinics.
     * @param int    $limit          Number of records to return (default: 1).
     * @return array                 Array of clinic records matching the criteria.
     */
    public function aiclinic_get_clinics_by_name_and_province($name, $province_code, $limit = 1)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_clinics', [
            'filters' => [
                'name' => sanitize_text_field($name),
                'province' => $province_code
            ],
            'limit' => absint($limit)
        ]) ?: [];
    }

    /**
     * Get clinics by province.
     *
     * @param string $province Province code.
     * @param int    $limit    Number of records (default: 10).
     * @return array Array of clinic records.
     */
    public function aiclinic_get_clinics_by_province($province_code, $limit = 10)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_clinics', [
            'filters' => ['province' => sanitize_text_field($province_code)],
            'limit'   => absint($limit)
        ]) ?: [];
    }

    /**
     * Get nearby clinics by coordinates.
     *
     * @param float $lat    Latitude.
     * @param float $lng    Longitude.
     * @param float $radius Radius in kilometers (default: 5).
     * @param int   $limit  Number of results (default: 10).
     * @return array Array of nearby clinics.
     */
    public function aiclinic_get_nearby_clinics($lat, $lng, $radius = 5, $limit = 10)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aiclinic_clinics';

        // Haversine formula for distance calculation
        $sql = $wpdb->prepare(
            "SELECT *, 
         (6371 * acos(cos(radians(%f)) * cos(radians(lat)) * 
         cos(radians(lng) - radians(%f)) + sin(radians(%f)) * 
         sin(radians(lat)))) AS distance 
         FROM `$table` 
         WHERE lat IS NOT NULL AND lng IS NOT NULL
         HAVING distance < %f 
         ORDER BY distance ASC 
         LIMIT %d",
            (float) $lat,
            (float) $lng,
            (float) $lat,
            (float) $radius,
            absint($limit)
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * ============================================================================
     * REVIEWS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert a new review.
     *
     * @param array $data {
     *     @type int    $clinic_id      Clinic ID (required)
     *     @type string $reviewer_name  Reviewer name
     *     @type int    $rating         Rating (1-5, required)
     *     @type string $comment        Review comment
     * }
     *
     * @return int|false The inserted review ID or false on failure.
     */
    public function aiclinic_insert_review($data)
    {
        $rating = absint($data['rating']);
        if ($rating < 1 || $rating > 5) {
            return false;
        }

        $data_to_insert = [
            'clinic_id'     => absint($data['clinic_id']),
            'reviewer_name' => sanitize_text_field($data['reviewer_name'] ?? ''),
            'rating'        => $rating,
            'comment'       => sanitize_textarea_field($data['comment'] ?? ''),
            'created_at'    => current_time('mysql'),
        ];

        $result = $this->helper->database->aiclinic_insert('aiclinic_reviews', $data_to_insert);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get reviews for a specific clinic.
     *
     * @param int   $clinic_id Clinic ID.
     * @param array $args      Pagination args (limit, offset, order_by, sort_direction).
     * @return array Array of review records.
     */
    public function aiclinic_get_reviews_by_clinic($clinic_id, $args = [])
    {
        $defaults = [
            'filters'        => ['clinic_id' => absint($clinic_id)],
            'limit'          => 10,
            'offset'         => 0,
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        return $this->helper->database->aiclinic_get_all('aiclinic_reviews', $args) ?: [];
    }

    /**
     * Get paginated reviews with counts.
     *
     * @param int   $clinic_id Clinic ID.
     * @param array $args      Pagination arguments.
     * @return array Paginated result with data, total, filtered.
     */
    public function aiclinic_get_reviews_paginated($clinic_id, $args = [])
    {
        $defaults = [
            'limit'  => 10,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        $data = $this->aiclinic_get_reviews_by_clinic($clinic_id, $args);
        $total = $this->helper->database->aiclinic_count_record('aiclinic_reviews', [
            'filters' => ['clinic_id' => absint($clinic_id)]
        ]) ?: 0;

        return [
            'data'     => $data,
            'total'    => $total,
            'filtered' => $total
        ];
    }

    /**
     * Get a single review by ID.
     *
     * @param int $review_id Review ID.
     * @return array|null Review record or null.
     */
    public function aiclinic_get_review_by_id($review_id)
    {
        return $this->helper->database->aiclinic_get_by_id('aiclinic_reviews', absint($review_id));
    }

    /**
     * Update a review.
     *
     * @param int   $review_id Review ID.
     * @param array $data      Data to update.
     * @return int|false Number of rows updated or false.
     */
    public function aiclinic_update_review($review_id, $data)
    {
        $data_to_update = [];

        if (isset($data['reviewer_name'])) {
            $data_to_update['reviewer_name'] = sanitize_text_field($data['reviewer_name']);
        }

        if (isset($data['rating'])) {
            $rating = absint($data['rating']);
            if ($rating >= 1 && $rating <= 5) {
                $data_to_update['rating'] = $rating;
            }
        }

        if (isset($data['comment'])) {
            $data_to_update['comment'] = sanitize_textarea_field($data['comment']);
        }

        if (empty($data_to_update)) {
            return false;
        }

        return $this->helper->database->aiclinic_update(
            'aiclinic_reviews',
            $data_to_update,
            ['id' => absint($review_id)]
        );
    }

    /**
     * Delete a review by ID.
     *
     * @param int $review_id Review ID.
     * @return int|false Number of rows deleted or false.
     */
    public function aiclinic_delete_review($review_id)
    {
        return $this->helper->database->aiclinic_delete_record_by_id('aiclinic_reviews', absint($review_id));
    }

    /**
     * Get average rating for a clinic.
     *
     * @param int $clinic_id Clinic ID.
     * @return float Average rating (0 if no reviews).
     */
    public function aiclinic_get_clinic_average_rating($clinic_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aiclinic_reviews';

        $sql = $wpdb->prepare(
            "SELECT AVG(rating) as avg_rating FROM `$table` WHERE clinic_id = %d",
            absint($clinic_id)
        );

        $result = $wpdb->get_var($sql);

        return $result ? round((float) $result, 1) : 0;
    }

    /**
     * Get review count for a clinic.
     *
     * @param int $clinic_id Clinic ID.
     * @return int Number of reviews.
     */
    public function aiclinic_get_clinic_review_count($clinic_id)
    {
        return $this->helper->database->aiclinic_count_record('aiclinic_reviews', [
            'filters' => ['clinic_id' => absint($clinic_id)]
        ]) ?: 0;
    }

    /**
     * ============================================================================
     * TAGS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert a new tag.
     *
     * @param string $name Tag name (required).
     * @return int|false The inserted tag ID or false on failure.
     */
    public function aiclinic_insert_tag($name)
    {
        $data = [
            'name'       => sanitize_text_field($name),
            'created_at' => current_time('mysql'),
        ];

        $result = $this->helper->database->aiclinic_insert('aiclinic_tags', $data);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get all tags with pagination.
     *
     * @param array $args Pagination arguments.
     * @return array Array of tag records.
     */
    public function aiclinic_get_tags($args = [])
    {
        $defaults = [
            'search' => '',
            'limit'  => 100,
            'offset' => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $args['limit'] = min((int) $args['limit'], 200);
        $args['column_search']  = 'name';
        $args['order_by']       = 'name';
        $args['sort_direction'] = 'ASC';

        return $this->helper->database->aiclinic_get_all('aiclinic_tags', $args) ?: [];
    }


    /**
     * Get a single tag by ID.
     *
     * @param int $tag_id Tag ID.
     * @return array|null Tag record or null.
     */
    public function aiclinic_get_tag_by_id($tag_id)
    {
        return $this->helper->database->aiclinic_get_by_id('aiclinic_tags', absint($tag_id));
    }

    /**
     * Get tag by name.
     *
     * @param string $name Tag name.
     * @return array|null Tag record or null.
     */
    public function aiclinic_get_tag_by_name($name)
    {
        $results = $this->helper->database->aiclinic_get_all('aiclinic_tags', [
            'filters' => ['name' => sanitize_text_field($name)],
            'limit'   => 1
        ]);

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Update a tag.
     *
     * @param int    $tag_id Tag ID.
     * @param string $name   New tag name.
     * @return int|false Number of rows updated or false.
     */
    public function aiclinic_update_tag($tag_id, $name)
    {
        return $this->helper->database->aiclinic_update(
            'aiclinic_tags',
            ['name' => sanitize_text_field($name)],
            ['id' => absint($tag_id)]
        );
    }

    /**
     * Delete a tag by ID (cascades to tag relations).
     *
     * @param int $tag_id Tag ID.
     * @return int|false Number of rows deleted or false.
     */
    public function aiclinic_delete_tag($tag_id)
    {
        return $this->helper->database->aiclinic_delete_record_by_id('aiclinic_tags', absint($tag_id));
    }

    /**
     * Get or create tag by name.
     *
     * @param string $name Tag name.
     * @return int Tag ID.
     */
    public function aiclinic_get_or_create_tag($name)
    {
        $tag = $this->aiclinic_get_tag_by_name($name);

        if ($tag) {
            return (int) $tag['id'];
        }

        return $this->aiclinic_insert_tag($name);
    }

    /**
     * ============================================================================
     * TAG RELATIONS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Assign a tag to a clinic.
     *
     * @param int $clinic_id Clinic ID.
     * @param int $tag_id    Tag ID.
     * @return int|false Number of rows inserted or false.
     */
    public function aiclinic_assign_tag_to_clinic($clinic_id, $tag_id)
    {
        // Check if relation already exists
        $exists = $this->helper->database->aiclinic_record_exists('aiclinic_tag_relations', [
            'clinic_id' => absint($clinic_id),
            'tag_id'    => absint($tag_id)
        ]);

        if ($exists) {
            return true; // Already assigned
        }

        return $this->helper->database->aiclinic_insert('aiclinic_tag_relations', [
            'clinic_id' => absint($clinic_id),
            'tag_id'    => absint($tag_id)
        ]);
    }

    /**
     * Remove a tag from a clinic.
     *
     * @param int $clinic_id Clinic ID.
     * @param int $tag_id    Tag ID.
     * @return int|false Number of rows deleted or false.
     */
    public function aiclinic_remove_tag_from_clinic($clinic_id, $tag_id)
    {
        return $this->helper->database->aiclinic_delete_records('aiclinic_tag_relations', [
            'clinic_id' => absint($clinic_id),
            'tag_id'    => absint($tag_id)
        ]);
    }

    /**
     * Get all tags for a specific clinic.
     *
     * @param int $clinic_id Clinic ID.
     * @return array Array of tag records.
     */
    public function aiclinic_get_tags_by_clinic($clinic_id)
    {
        global $wpdb;

        $tags_table = $wpdb->prefix . 'aiclinic_tags';
        $relations_table = $wpdb->prefix . 'aiclinic_tag_relations';

        $sql = $wpdb->prepare(
            "SELECT t.* FROM `$tags_table` t
         INNER JOIN `$relations_table` tr ON t.id = tr.tag_id
         WHERE tr.clinic_id = %d
         ORDER BY t.name ASC",
            absint($clinic_id)
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Get all clinics with a specific tag.
     *
     * @param int   $tag_id Tag ID.
     * @param array $args   Pagination arguments.
     * @return array Array of clinic records.
     */
    public function aiclinic_get_clinics_by_tag($tag_id, $args = [])
    {
        global $wpdb;

        $defaults = [
            'limit'          => 10,
            'offset'         => 0,
            'order_by'       => 'name',
            'sort_direction' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        $clinics_table = $wpdb->prefix . 'aiclinic_clinics';
        $relations_table = $wpdb->prefix . 'aiclinic_tag_relations';

        $order_by = esc_sql($args['order_by']);
        $sort_direction = $args['sort_direction'] === 'DESC' ? 'DESC' : 'ASC';

        $sql = $wpdb->prepare(
            "SELECT c.* FROM `$clinics_table` c
         INNER JOIN `$relations_table` tr ON c.id = tr.clinic_id
         WHERE tr.tag_id = %d
         ORDER BY c.$order_by $sort_direction
         LIMIT %d OFFSET %d",
            absint($tag_id),
            absint($args['limit']),
            absint($args['offset'])
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Sync tags for a clinic (replaces all existing tags).
     *
     * @param int   $clinic_id Clinic ID.
     * @param array $tag_ids   Array of tag IDs.
     * @return bool True on success, false on failure.
     */
    public function aiclinic_sync_clinic_tags($clinic_id, array $tag_ids)
    {
        // First, remove all existing tags
        $this->helper->database->aiclinic_delete_records('aiclinic_tag_relations', [
            'clinic_id' => absint($clinic_id)
        ]);

        // Then add new tags
        $success = true;
        foreach ($tag_ids as $tag_id) {
            $result = $this->aiclinic_assign_tag_to_clinic($clinic_id, $tag_id);
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Sync tags by name (creates tags if they don't exist).
     *
     * @param int   $clinic_id Clinic ID.
     * @param array $tag_names Array of tag names.
     * @return bool True on success, false on failure.
     */
    public function aiclinic_sync_clinic_tags_by_name($clinic_id, array $tag_names)
    {
        $tag_ids = [];

        foreach ($tag_names as $name) {
            $tag_id = $this->aiclinic_get_or_create_tag($name);
            if ($tag_id) {
                $tag_ids[] = $tag_id;
            }
        }

        return $this->aiclinic_sync_clinic_tags($clinic_id, $tag_ids);
    }

    /**
     * ============================================================================
     * PLUGIN SETTINGS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert or update a plugin setting.
     *
     * @param string $option_name  Setting name (required).
     * @param mixed  $option_value Setting value (required).
     * @return int|false Number of rows affected or false on failure.
     */
    public function aiclinic_set_setting($option_name, $option_value)
    {
        $data = [
            'option_name'  => sanitize_key($option_name),
            'option_value' => is_array($option_value)
                ? wp_json_encode($option_value)
                : sanitize_textarea_field($option_value),
            'updated_at'   => current_time('mysql')
        ];

        return $this->helper->database->aiclinic_upsert(
            'aiclinic_plugin_settings',
            $data,
            ['option_name' => sanitize_key($option_name)]
        );
    }

    /**
     * Get a plugin setting value.
     *
     * @param string $option_name Setting name.
     * @param mixed  $default     Default value if not found.
     * @param bool   $decode_json Whether to decode JSON values (default: false).
     * @return mixed Setting value or default.
     */
    public function aiclinic_get_setting($option_name, $default = '', $decode_json = false)
    {
        $results = $this->helper->database->aiclinic_get_all('aiclinic_plugin_settings', [
            'filters' => ['option_name' => sanitize_key($option_name)],
            'limit'   => 1
        ]);

        if (empty($results)) {
            return $default;
        }

        $value = $results[0]['option_value'];

        if ($decode_json) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }

        return $value;
    }

    /**
     * Get multiple settings at once.
     *
     * @param array $option_names Array of setting names.
     * @param bool  $decode_json  Whether to decode JSON values.
     * @return array Associative array of settings [option_name => option_value].
     */
    public function aiclinic_get_settings(array $option_names, $decode_json = false)
    {
        if (empty($option_names)) {
            return [];
        }

        $results = $this->helper->database->aiclinic_get_all('aiclinic_plugin_settings', [
            'filters' => ['option_name' => $option_names]
        ]);

        $settings = array_fill_keys($option_names, '');

        foreach ($results as $row) {
            $value = $row['option_value'];

            if ($decode_json) {
                $decoded = json_decode($value, true);
                $value = $decoded !== null ? $decoded : $value;
            }

            $settings[$row['option_name']] = $value;
        }

        return $settings;
    }

    /**
     * Get all plugin settings.
     *
     * @param array $args Pagination arguments.
     * @return array Array of all settings.
     */
    public function aiclinic_get_all_settings($args = [])
    {
        $defaults = [
            'limit'          => 100,
            'offset'         => 0,
            'order_by'       => 'option_name',
            'sort_direction' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        return $this->helper->database->aiclinic_get_all('aiclinic_plugin_settings', $args) ?: [];
    }

    /**
     * Get paginated settings with search.
     *
     * @param array $args {
     *     @type string $search         Search keyword
     *     @type int    $limit          Records per page
     *     @type int    $offset         Offset for pagination
     *     @type string $order_by       Column to sort by
     *     @type string $sort_direction Sort direction
     * }
     * @return array Paginated result with data, total, filtered.
     */
    public function aiclinic_get_settings_paginated($args = [])
    {
        $defaults = [
            'search'         => '',
            'column_search'  => 'option_name',
            'limit'          => 20,
            'offset'         => 0,
            'order_by'       => 'option_name',
            'sort_direction' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

        $data = $this->helper->database->aiclinic_get_all('aiclinic_plugin_settings', $args);

        $count_args = [
            'search'        => $args['search'],
            'column_search' => $args['column_search']
        ];

        return [
            'data'     => $data ?: [],
            'total'    => $this->helper->database->aiclinic_count_record('aiclinic_plugin_settings', []) ?: 0,
            'filtered' => $this->helper->database->aiclinic_count_record('aiclinic_plugin_settings', $count_args) ?: 0
        ];
    }

    /**
     * Check if a setting exists.
     *
     * @param string $option_name Setting name.
     * @return bool True if exists, false otherwise.
     */
    public function aiclinic_setting_exists($option_name): bool
    {
        return $this->helper->database->aiclinic_record_exists(
            'aiclinic_plugin_settings',
            ['option_name' => sanitize_key($option_name)]
        );
    }

    /**
     * Delete a setting.
     *
     * @param string $option_name Setting name to delete.
     * @return int|false Number of rows deleted or false on failure.
     */
    public function aiclinic_delete_setting($option_name)
    {
        return $this->helper->database->aiclinic_delete_records(
            'aiclinic_plugin_settings',
            ['option_name' => sanitize_key($option_name)]
        );
    }

    /**
     * Bulk update multiple settings.
     *
     * @param array $settings Associative array [option_name => option_value].
     * @return bool True if all succeeded, false otherwise.
     */
    public function aiclinic_bulk_update_settings(array $settings): bool
    {
        $success = true;

        foreach ($settings as $name => $value) {
            $result = $this->aiclinic_set_setting($name, $value);
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Delete multiple settings.
     *
     * @param array $option_names Array of setting names to delete.
     * @return int Number of deleted settings.
     */
    public function aiclinic_bulk_delete_settings(array $option_names): int
    {
        $deleted = 0;

        foreach ($option_names as $name) {
            $result = $this->aiclinic_delete_setting($name);
            if ($result !== false) {
                $deleted += $result;
            }
        }

        return $deleted;
    }

    /**
     * Get settings by prefix (e.g., 'ai_', 'email_').
     *
     * @param string $prefix Setting name prefix.
     * @return array Array of matching settings.
     */
    public function aiclinic_get_settings_by_prefix($prefix)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_plugin_settings', [
            'search'        => sanitize_text_field($prefix),
            'column_search' => 'option_name',
            'limit'         => 100
        ]) ?: [];
    }

    /**
     * ============================================================================
     * CUSTOMERS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert a new customer.
     *
     * @param array $data {
     *     @type int    $user_id        WordPress user ID (required)
     *     @type string $customer_key   Unique customer key (required)
     *     @type string $customer_name  Customer name (required)
     *     @type string $pricing_plan   Pricing plan (BASIC|GOLD)
     *     @type string $domain         Customer domain
     *     @type string $customer_status Customer status (ACTIVE|INACTIVE|UNPAID)
     * }
     * @return int|false The inserted customer ID or false on failure.
     */
    public function aiclinic_insert_customer($data)
    {
        $data_to_insert = [
            'user_id'         => absint($data['user_id']),
            'customer_key'    => sanitize_text_field($data['customer_key']),
            'customer_name'   => sanitize_text_field($data['customer_name']),
            'pricing_plan'    => in_array($data['pricing_plan'] ?? '', ['BASIC', 'GOLD'])
                ? $data['pricing_plan']
                : 'BASIC',
            'domain'          => !empty($data['domain'])
                ? sanitize_text_field($data['domain'])
                : null,
            'register_date'   => current_time('mysql'),
            'customer_status' => in_array($data['customer_status'] ?? '', ['ACTIVE', 'INACTIVE', 'UNPAID'])
                ? $data['customer_status']
                : 'UNPAID',
            'updated_at'      => current_time('mysql'),
        ];

        $result = $this->helper->database->aiclinic_insert('aiclinic_customers', $data_to_insert);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get paginated list of customers with search and filters.
     *
     * @param array $args {
     *     @type string $search         Search keyword (searches in customer_name, domain)
     *     @type string $pricing_plan   Filter by pricing plan
     *     @type string $customer_status Filter by customer status
     *     @type int    $limit          Records per page
     *     @type int    $offset         Offset for pagination
     *     @type string $order_by       Column to sort by
     *     @type string $sort_direction Sort direction
     * }
     * @return array|false Array of customer records or false on error.
     */
    public function aiclinic_get_customers($args = [])
    {
        $defaults = [
            'search'          => '',
            'column_search'   => 'customer_name',
            'pricing_plan'    => '',
            'customer_status' => '',
            'limit'           => 10,
            'offset'          => 0,
            'order_by'        => 'register_date',
            'sort_direction'  => 'DESC',
            'filters'         => []
        ];

        $args = wp_parse_args($args, $defaults);

        // Build filters
        if (!empty($args['pricing_plan'])) {
            $args['filters']['pricing_plan'] = sanitize_text_field($args['pricing_plan']);
        }

        if (!empty($args['customer_status'])) {
            $args['filters']['customer_status'] = sanitize_text_field($args['customer_status']);
        }

        return $this->helper->database->aiclinic_get_all('aiclinic_customers', $args);
    }

    /**
     * Get paginated customers with total counts.
     *
     * @param array $args Same as aiclinic_get_customers().
     * @return array Paginated result with data, total, filtered.
     */
    public function aiclinic_get_customers_paginated($args = [])
    {
        // Prepare count arguments
        $count_args = [
            'search'        => $args['search'] ?? '',
            'column_search' => $args['column_search'] ?? 'customer_name',
            'filters'       => []
        ];

        if (!empty($args['pricing_plan'])) {
            $count_args['filters']['pricing_plan'] = sanitize_text_field($args['pricing_plan']);
        }

        if (!empty($args['customer_status'])) {
            $count_args['filters']['customer_status'] = sanitize_text_field($args['customer_status']);
        }

        return [
            'data'     => $this->aiclinic_get_customers($args) ?: [],
            'total'    => $this->helper->database->aiclinic_count_record('aiclinic_customers', []) ?: 0,
            'filtered' => $this->helper->database->aiclinic_count_record('aiclinic_customers', $count_args) ?: 0
        ];
    }

    /**
     * Get a single customer by ID.
     *
     * @param int $customer_id Customer ID.
     * @return array|null Customer record or null if not found.
     */
    public function aiclinic_get_customer_by_id($customer_id)
    {
        return $this->helper->database->aiclinic_get_by_id('aiclinic_customers', absint($customer_id));
    }

    /**
     * Get customer by WordPress user ID.
     *
     * @param int $user_id WordPress user ID.
     * @return array|null Customer record or null if not found.
     */
    public function aiclinic_get_customer_by_user_id($user_id)
    {
        $results = $this->helper->database->aiclinic_get_all('aiclinic_customers', [
            'filters' => ['user_id' => absint($user_id)],
            'limit'   => 1
        ]);

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Get customer by customer key.
     *
     * @param string $customer_key Unique customer key.
     * @return array|null Customer record or null if not found.
     */
    public function aiclinic_get_customer_by_key($customer_key)
    {
        $results = $this->helper->database->aiclinic_get_all('aiclinic_customers', [
            'filters' => ['customer_key' => sanitize_text_field($customer_key)],
            'limit'   => 1
        ]);

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Get customer by domain.
     *
     * @param string $domain Customer domain.
     * @return array|null Customer record or null if not found.
     */
    public function aiclinic_get_customer_by_domain($domain)
    {
        $results = $this->helper->database->aiclinic_get_all('aiclinic_customers', [
            'filters' => ['domain' => sanitize_text_field($domain)],
            'limit'   => 1
        ]);

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Update a customer record.
     *
     * @param int   $customer_id Customer ID to update.
     * @param array $data        Data to update.
     * @return int|false Number of rows updated or false on failure.
     */
    public function aiclinic_update_customer($customer_id, $data)
    {
        $data_to_update = [];

        if (isset($data['customer_name'])) {
            $data_to_update['customer_name'] = sanitize_text_field($data['customer_name']);
        }

        if (isset($data['pricing_plan']) && in_array($data['pricing_plan'], ['BASIC', 'GOLD'])) {
            $data_to_update['pricing_plan'] = $data['pricing_plan'];
        }

        if (isset($data['domain'])) {
            $data_to_update['domain'] = !empty($data['domain'])
                ? sanitize_text_field($data['domain'])
                : null;
        }

        if (isset($data['customer_status']) && in_array($data['customer_status'], ['ACTIVE', 'INACTIVE', 'UNPAID'])) {
            $data_to_update['customer_status'] = $data['customer_status'];
        }

        if (empty($data_to_update)) {
            return false;
        }

        $data_to_update['updated_at'] = current_time('mysql');

        return $this->helper->database->aiclinic_update(
            'aiclinic_customers',
            $data_to_update,
            ['id' => absint($customer_id)]
        );
    }

    /**
     * Update customer status.
     *
     * @param int    $customer_id Customer ID.
     * @param string $status      New status (ACTIVE|INACTIVE|UNPAID).
     * @return int|false Number of rows updated or false on failure.
     */
    public function aiclinic_update_customer_status($customer_id, $status)
    {
        if (!in_array($status, ['ACTIVE', 'INACTIVE', 'UNPAID'])) {
            return false;
        }

        return $this->helper->database->aiclinic_update(
            'aiclinic_customers',
            [
                'customer_status' => $status,
                'updated_at'      => current_time('mysql')
            ],
            ['id' => absint($customer_id)]
        );
    }

    /**
     * Update customer pricing plan.
     *
     * @param int    $customer_id Customer ID.
     * @param string $plan        New plan (BASIC|GOLD).
     * @return int|false Number of rows updated or false on failure.
     */
    public function aiclinic_update_customer_plan($customer_id, $plan)
    {
        if (!in_array($plan, ['BASIC', 'GOLD'])) {
            return false;
        }

        return $this->helper->database->aiclinic_update(
            'aiclinic_customers',
            [
                'pricing_plan' => $plan,
                'updated_at'   => current_time('mysql')
            ],
            ['id' => absint($customer_id)]
        );
    }

    /**
     * Delete a customer by ID.
     *
     * @param int $customer_id Customer ID to delete.
     * @return int|false Number of rows deleted or false on failure.
     */
    public function aiclinic_delete_customer($customer_id)
    {
        return $this->helper->database->aiclinic_delete_record_by_id('aiclinic_customers', absint($customer_id));
    }

    /**
     * Bulk delete customers by IDs.
     *
     * @param array $customer_ids Array of customer IDs.
     * @return int Number of deleted customers.
     */
    public function aiclinic_bulk_delete_customers(array $customer_ids): int
    {
        $deleted = 0;

        foreach ($customer_ids as $id) {
            $result = $this->aiclinic_delete_customer($id);
            if ($result !== false) {
                $deleted += $result;
            }
        }

        return $deleted;
    }

    /**
     * Count customers by status.
     *
     * @param string $status Customer status (ACTIVE|INACTIVE|UNPAID).
     * @return int Number of customers.
     */
    public function aiclinic_count_customers_by_status($status = '')
    {
        $args = [];

        if (!empty($status)) {
            $args['filters'] = ['customer_status' => sanitize_text_field($status)];
        }

        return $this->helper->database->aiclinic_count_record('aiclinic_customers', $args) ?: 0;
    }

    /**
     * Count customers by pricing plan.
     *
     * @param string $plan Pricing plan (BASIC|GOLD).
     * @return int Number of customers.
     */
    public function aiclinic_count_customers_by_plan($plan = '')
    {
        $args = [];

        if (!empty($plan)) {
            $args['filters'] = ['pricing_plan' => sanitize_text_field($plan)];
        }

        return $this->helper->database->aiclinic_count_record('aiclinic_customers', $args) ?: 0;
    }

    /**
     * Get customers by status.
     *
     * @param string $status Customer status.
     * @param int    $limit  Number of records.
     * @return array Array of customer records.
     */
    public function aiclinic_get_customers_by_status($status, $limit = 10)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_customers', [
            'filters' => ['customer_status' => sanitize_text_field($status)],
            'limit'   => absint($limit)
        ]) ?: [];
    }

    /**
     * Check if customer key exists.
     *
     * @param string $customer_key Customer key to check.
     * @return bool True if exists, false otherwise.
     */
    public function aiclinic_customer_key_exists($customer_key): bool
    {
        return $this->helper->database->aiclinic_record_exists(
            'aiclinic_customers',
            ['customer_key' => sanitize_text_field($customer_key)]
        );
    }

    /**
     * Check if domain exists.
     *
     * @param string $domain Domain to check.
     * @return bool True if exists, false otherwise.
     */
    public function aiclinic_domain_exists($domain): bool
    {
        return $this->helper->database->aiclinic_record_exists(
            'aiclinic_customers',
            ['domain' => sanitize_text_field($domain)]
        );
    }

    /**
     * Generate unique customer key.
     *
     * @param string $prefix Optional prefix (default: 'CUST').
     * @return string Unique customer key.
     */
    public function aiclinic_generate_customer_key($prefix = 'CUST'): string
    {
        do {
            $key = strtoupper($prefix . '_' . wp_generate_password(12, false));
        } while ($this->aiclinic_customer_key_exists($key));

        return $key;
    }

    /**
     * ============================================================================
     * ERROR LOGS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert an error log entry.
     *
     * @param array $data {
     *     @type string $action  The action being executed when error occurred (required)
     *     @type string $message The error message to be logged (required)
     *     @type mixed  $context Additional context (array will be JSON encoded)
     * }
     * @return int|false The inserted log ID or false on failure.
     */
    public function aiclinic_insert_error_log($data)
    {
        $data_to_insert = [
            'action'     => sanitize_text_field($data['action'] ?? ''),
            'message'    => sanitize_textarea_field($data['message'] ?? ''),
            'context'    => is_array($data['context'] ?? '')
                ? wp_json_encode($data['context'])
                : sanitize_textarea_field($data['context'] ?? ''),
            'created_at' => current_time('mysql'),
        ];

        $result = $this->helper->database->aiclinic_insert('aiclinic_plugin_error_logs', $data_to_insert);

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get paginated error logs with search and filters.
     *
     * @param array $args {
     *     @type string $search         Search keyword (searches in action or message)
     *     @type string $action         Filter by action
     *     @type int    $limit          Records per page
     *     @type int    $offset         Offset for pagination
     *     @type string $order_by       Column to sort by
     *     @type string $sort_direction Sort direction
     * }
     * @return array|false Array of log records or false on error.
     */
    public function aiclinic_get_error_logs($args = [])
    {
        $defaults = [
            'search'         => '',
            'column_search'  => 'action',
            'action'         => '',
            'limit'          => 20,
            'offset'         => 0,
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC',
            'filters'        => []
        ];

        $args = wp_parse_args($args, $defaults);

        // Add action filter if specified
        if (!empty($args['action'])) {
            $args['filters']['action'] = sanitize_text_field($args['action']);
        }

        return $this->helper->database->aiclinic_get_all('aiclinic_plugin_error_logs', $args);
    }

    /**
     * Get paginated error logs with counts.
     *
     * @param array $args Same as aiclinic_get_error_logs().
     * @return array Paginated result with data, total, filtered.
     */
    public function aiclinic_get_error_logs_paginated($args = [])
    {
        $count_args = [
            'search'        => $args['search'] ?? '',
            'column_search' => $args['column_search'] ?? 'action',
            'filters'       => []
        ];

        if (!empty($args['action'])) {
            $count_args['filters']['action'] = sanitize_text_field($args['action']);
        }

        return [
            'data'     => $this->aiclinic_get_error_logs($args) ?: [],
            'total'    => $this->helper->database->aiclinic_count_record('aiclinic_plugin_error_logs', []) ?: 0,
            'filtered' => $this->helper->database->aiclinic_count_record('aiclinic_plugin_error_logs', $count_args) ?: 0
        ];
    }

    /**
     * Get a single error log by ID.
     *
     * @param int $log_id Log ID.
     * @return array|null Log record or null if not found.
     */
    public function aiclinic_get_error_log_by_id($log_id)
    {
        return $this->helper->database->aiclinic_get_by_id('aiclinic_plugin_error_logs', absint($log_id));
    }

    /**
     * Delete an error log by ID.
     *
     * @param int $log_id Log ID to delete.
     * @return int|false Number of rows deleted or false on failure.
     */
    public function aiclinic_delete_error_log($log_id)
    {
        return $this->helper->database->aiclinic_delete_record_by_id('aiclinic_plugin_error_logs', absint($log_id));
    }

    /**
     * Bulk delete error logs by IDs.
     *
     * @param array $log_ids Array of log IDs.
     * @return int Number of deleted logs.
     */
    public function aiclinic_bulk_delete_error_logs(array $log_ids): int
    {
        $deleted = 0;

        foreach ($log_ids as $id) {
            $result = $this->aiclinic_delete_error_log($id);
            if ($result !== false) {
                $deleted += $result;
            }
        }

        return $deleted;
    }

    /**
     * Delete error logs older than specified days.
     *
     * @param int $days Number of days to keep logs (default: 30).
     * @return int|false Number of deleted rows or false on failure.
     */
    public function aiclinic_cleanup_old_error_logs($days = 30)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'aiclinic_plugin_error_logs';
        $date_threshold = date('Y-m-d H:i:s', strtotime("-$days days"));

        $sql = $wpdb->prepare(
            "DELETE FROM `$table` WHERE `created_at` < %s",
            $date_threshold
        );

        return $wpdb->query($sql);
    }

    /**
     * Count error logs by action.
     *
     * @param string $action Action name.
     * @return int Number of logs.
     */
    public function aiclinic_count_error_logs_by_action($action = '')
    {
        $args = [];

        if (!empty($action)) {
            $args['filters'] = ['action' => sanitize_text_field($action)];
        }

        return $this->helper->database->aiclinic_count_record('aiclinic_plugin_error_logs', $args) ?: 0;
    }

    /**
     * Get error logs by action.
     *
     * @param string $action Action name.
     * @param int    $limit  Number of records (default: 20).
     * @return array Array of log records.
     */
    public function aiclinic_get_error_logs_by_action($action, $limit = 20)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_plugin_error_logs', [
            'filters' => ['action' => sanitize_text_field($action)],
            'limit'   => absint($limit),
            'order_by' => 'created_at',
            'sort_direction' => 'DESC'
        ]) ?: [];
    }

    /**
     * Get recent error logs (last N logs).
     *
     * @param int $limit Number of recent logs (default: 10).
     * @return array Array of recent log records.
     */
    public function aiclinic_get_recent_error_logs($limit = 10): array
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_plugin_error_logs', [
            'limit'          => absint($limit),
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC'
        ]) ?: [];
    }

    /**
     * Batch insert multiple error logs.
     *
     * @param array $logs Array of log entries.
     * @return bool True if all inserts succeeded, false otherwise.
     */
    public function aiclinic_batch_insert_error_logs(array $logs): bool
    {
        if (empty($logs)) {
            return true;
        }

        $success = true;

        foreach ($logs as $log) {
            $result = $this->aiclinic_insert_error_log($log);
            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clear all error logs.
     *
     * @return bool True on success, false on failure.
     */
    public function aiclinic_clear_all_error_logs(): bool
    {
        return $this->helper->database->aiclinic_truncate_table('aiclinic_plugin_error_logs');
    }

    /**
     * ============================================================================
     * CLINIC EDIT SUGGESTIONS TABLE - CRUD OPERATIONS
     * ============================================================================
     */

    /**
     * Insert a new clinic edit suggestion.
     *
     * @param array $data {
     *     @type string $suggestion_type  CREATE | EDIT
     *     @type int    $clinic_id        Clinic ID
     *     @type array  $suggested_fields Proposed field changes
     *     @type string  $suggested_by Source of suggestion: OWNER | OTHER
     * }
     *
     * @return int|false The inserted suggestion ID or false on failure.
     */
    public function aiclinic_insert_clinic_suggestion($data)
    {
        $suggestion_type = $data['suggestion_type'] ?? 'EDIT';
        $suggested_by = $data['suggested_by'] ?? 'OTHER';

        if (!in_array($suggestion_type, ['CREATE', 'EDIT'], true)) {
            return false;
        }

        if (
            empty($data['suggested_fields']) ||
            !is_array($data['suggested_fields'])
        ) {
            return false;
        }

        if ($suggestion_type === 'EDIT' && empty($data['clinic_id'])) {
            return false;
        }

        if (empty($data['user_id'])) {
            return false;
        }

        $data_to_insert = [
            'suggestion_type' => $suggestion_type,
            'clinic_id'       => $suggestion_type === 'EDIT'
                ? absint($data['clinic_id'])
                : null,
            'user_id'          => $data['user_id'],
            'suggested_fields' => wp_json_encode($data['suggested_fields']),
            'status'           => 'PENDING',
            'created_at'       => current_time('mysql'),
            'suggested_by'     => $suggested_by,
        ];

        $result = $this->helper->database->aiclinic_insert(
            'aiclinic_clinic_suggestions',
            $data_to_insert
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Get edit suggestions by clinic ID. (EDIT only)
     *
     * @param int   $clinic_id Clinic ID.
     * @param array $args      Pagination args.
     * @return array List of edit suggestions.
     */
    public function aiclinic_get_clinic_suggestions_by_clinic($clinic_id, $args = [])
    {
        $defaults = [
            'filters' => [
                'clinic_id'       => absint($clinic_id),
                'suggestion_type' => 'EDIT',
            ],
            'limit'          => 10,
            'offset'         => 0,
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        return $this->helper->database->aiclinic_get_all(
            'aiclinic_clinic_suggestions',
            $args
        ) ?: [];
    }

    /**
     * Get paginated clinic edit/create suggestions.
     */
    public function aiclinic_get_clinic_edit_suggestions_paginated($args = [])
    {
        $defaults = [
            'filters' => [],
            'limit'   => 10,
            'offset'  => 0,
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $data = $this->helper->database->aiclinic_get_all(
            'aiclinic_clinic_suggestions',
            $args
        ) ?: [];

        foreach ($data as &$item) {
            if (!empty($item['suggested_fields'])) {
                $item['suggested_fields'] = json_decode($item['suggested_fields'], true);
            }
        }

        $total = $this->helper->database->aiclinic_count_record(
            'aiclinic_clinic_suggestions',
            ['filters' => $args['filters']]
        ) ?: 0;

        return [
            'data'     => $data,
            'total'    => $total,
            'filtered' => $total,
        ];
    }


    /**
     * Get a single clinic edit suggestion by ID.
     *
     * @param int $suggestion_id Suggestion ID.
     * @return array|null Suggestion record.
     */
    public function aiclinic_get_clinic_suggestion_by_id($suggestion_id)
    {
        $record = $this->helper->database->aiclinic_get_by_id(
            'aiclinic_clinic_suggestions',
            absint($suggestion_id)
        );

        if ($record && !empty($record['suggested_fields'])) {
            $record['suggested_fields'] = json_decode($record['suggested_fields'], true);
        }

        return $record;
    }

    /**
     * Update clinic edit suggestion status (approve / reject).
     *
     * @param int   $suggestion_id Suggestion ID.
     * @param array $data {
     *     @type string $status        New status (APPROVED | REJECTED)
     *     @type string $review_note   Optional review note
     * }
     *
     * @return int|false Number of rows updated or false.
     */
    public function aiclinic_update_clinic_suggestion_status($suggestion_id, $data)
    {
        if (empty($data['status']) || !in_array($data['status'], ['APPROVED', 'REJECTED'], true)) {
            return false;
        }

        if (empty($data['user_id'])) {
            return false;
        }

        $data_to_update = [
            'status'      => $data['status'],
            'reviewed_by' => $data['user_id'],
            'reviewed_at' => current_time('mysql'),
        ];

        if (isset($data['review_note'])) {
            $data_to_update['review_note'] = sanitize_textarea_field($data['review_note']);
        }

        return $this->helper->database->aiclinic_update(
            'aiclinic_clinic_suggestions',
            $data_to_update,
            ['id' => absint($suggestion_id)]
        );
    }

    /**
     * Delete a clinic edit suggestion by ID.
     *
     * @param int $suggestion_id Suggestion ID.
     * @return int|false Number of rows deleted or false.
     */
    public function aiclinic_delete_clinic_suggestion($suggestion_id)
    {
        return $this->helper->database->aiclinic_delete_record_by_id(
            'aiclinic_clinic_suggestions',
            absint($suggestion_id)
        );
    }
}
