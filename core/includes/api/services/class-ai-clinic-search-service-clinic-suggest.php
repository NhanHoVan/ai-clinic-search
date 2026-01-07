<?php

/**
 * Class Ai_Clinic_Service_Clinic_Suggest
 *
 * Handles logic for clinic suggest service
 * 
 * @package AICLINIC
 */
class Ai_Clinic_Service_Clinic_Suggest
{
    /**
     * @var Ai_Clinic_Search_DB Database instance
     */
    private $database;

    /**
     * helper
     *
     * @var		Ai_Clinic_Search_Helpers
     * @since   1.0.0
     */
    private $helper;

    public function __construct()
    {
        $this->database = new Ai_Clinic_Search_DB();
        $this->helper = new Ai_Clinic_Search_Helpers();
    }

    /**
     * Create a clinic suggestion (CREATE or EDIT).
     *
     * @param array $data {
     *     @type string      $suggestion_type  CREATE | EDIT
     *     @type int|null    $clinic_id        Required for EDIT
     *     @type array       $suggested_fields Sanitized suggested fields
     *     @type string|null $suggested_by     OWNER | OTHER
     * }
     *
     * @return int|false Suggestion ID on success, false on failure.
     */
    public function create_clinic_suggestion(array $data)
    {
        return $this->database->aiclinic_insert_clinic_suggestion($data);
    }

    /**
     * Map clinics for management-user: Add the `is_disable` field based on PENDING suggestions.
     *
     * @param array $clinics List of clinics
     * @return array
     */
    public function map_user_clinics_with_suggestions(array $clinics): array
    {
        if (empty($clinics)) {
            return [];
        }

        foreach ($clinics as &$clinic) {
            $clinic_id = $clinic['id'] ?? 0;

            if (!$clinic_id) {
                $clinic['is_disable'] = false;
                continue;
            }

            $suggestions = $this->database->aiclinic_get_clinic_suggestions_by_clinic($clinic_id);

            $has_pending = false;
            foreach ($suggestions as $sugg) {
                if (!empty($sugg['status']) && $sugg['status'] === 'PENDING') {
                    $has_pending = true;
                    break;
                }
            }

            $clinic['is_disable'] = $has_pending;
        }

        return $clinics;
    }
}
