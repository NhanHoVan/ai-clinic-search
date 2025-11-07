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
     *     @type string $address        Full address
     *     @type string $province       Province/city name
     *     @type string $district       District/area name
     *     @type float  $lat            Latitude
     *     @type float  $lng            Longitude
     *     @type string $phone          Contact phone
     *     @type string $email          Contact email
     *     @type string $description    Clinic description
     *     @type string $verify_status  Verification status (AI_DETECTED|ADMIN_VERIFIED|OWNER_VERIFIED)
     *     @type string $is_active      Display status (ACTIVE|HIDDEN)
     * }
     *
     * @return int|false The inserted clinic ID or false on failure.
     */
    public function aiclinic_insert_clinic($data)
    {
        $data_to_insert = [
            'name'          => sanitize_text_field($data['name']),
            'address'       => sanitize_textarea_field($data['address'] ?? ''),
            'province'      => sanitize_text_field($data['province'] ?? ''),
            'district'      => sanitize_text_field($data['district'] ?? ''),
            'lat'           => !empty($data['lat']) ? (float) $data['lat'] : null,
            'lng'           => !empty($data['lng']) ? (float) $data['lng'] : null,
            'phone'         => sanitize_text_field($data['phone'] ?? ''),
            'email'         => sanitize_email($data['email'] ?? ''),
            'description'   => sanitize_textarea_field($data['description'] ?? ''),
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
     * Get paginated list of clinics with search and filters.
     *
     * @param array $args {
     *     @type string $search         Search keyword (searches in name, address)
     *     @type string $province       Filter by province
     *     @type string $district       Filter by district
     *     @type string $verify_status  Filter by verification status
     *     @type string $is_active      Filter by active status
     *     @type int    $limit          Records per page (default: 10)
     *     @type int    $offset         Offset for pagination (default: 0)
     *     @type string $order_by       Column to sort by (default: 'created_at')
     *     @type string $sort_direction ASC or DESC (default: 'DESC')
     * }
     *
     * @return array|false Array of clinic records or false on error.
     */
    public function aiclinic_get_clinics($args = [])
    {
        $defaults = [
            'search'         => '',
            'column_search'  => 'name',
            'province'       => '',
            'district'       => '',
            'verify_status'  => '',
            'is_active'      => '',
            'limit'          => 10,
            'offset'         => 0,
            'order_by'       => 'created_at',
            'sort_direction' => 'DESC',
            'filters'        => []
        ];

        $args = wp_parse_args($args, $defaults);

        // Build filters
        if (!empty($args['province'])) {
            $args['filters']['province'] = sanitize_text_field($args['province']);
        }

        if (!empty($args['district'])) {
            $args['filters']['district'] = sanitize_text_field($args['district']);
        }

        if (!empty($args['verify_status'])) {
            $args['filters']['verify_status'] = sanitize_text_field($args['verify_status']);
        }

        if (!empty($args['is_active'])) {
            $args['filters']['is_active'] = sanitize_text_field($args['is_active']);
        }

        return $this->helper->database->aiclinic_get_all('aiclinic_clinics', $args);
    }

    /**
     * Get paginated clinics with total counts (for DataTables).
     *
     * @param array $args Same as aiclinic_get_clinics()
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

        return [
            'data'     => $this->aiclinic_get_clinics($args) ?: [],
            'total'    => $this->helper->database->aiclinic_count_record('aiclinic_clinics', []) ?: 0,
            'filtered' => $this->helper->database->aiclinic_count_record('aiclinic_clinics', $count_args) ?: 0
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

        if (isset($data['description'])) {
            $data_to_update['description'] = sanitize_textarea_field($data['description']);
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
     * Get clinics by province.
     *
     * @param string $province Province name.
     * @param int    $limit    Number of records (default: 10).
     * @return array Array of clinic records.
     */
    public function aiclinic_get_clinics_by_province($province, $limit = 10)
    {
        return $this->helper->database->aiclinic_get_all('aiclinic_clinics', [
            'filters' => ['province' => sanitize_text_field($province)],
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
            'search'         => '',
            'column_search'  => 'name',
            'limit'          => 100,
            'offset'         => 0,
            'order_by'       => 'name',
            'sort_direction' => 'ASC'
        ];

        $args = wp_parse_args($args, $defaults);

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
}
