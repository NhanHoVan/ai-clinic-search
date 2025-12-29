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
}
