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

    /**
     * @var Ai_Clinic_Service_Clinic Clinic instance
     */
    private $clinic_service;

    public function __construct()
    {
        $this->database = new Ai_Clinic_Search_DB();
        $this->helper = new Ai_Clinic_Search_Helpers();
        $this->clinic_service = new Ai_Clinic_Service_Clinic();
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

    /**
     * Review a clinic suggestion (approve or reject)
     *
     * @param int   $suggest_id
     * @param array $review_fields
     * @param int   $user_id
     * @return array|WP_Error
     */
    public function review_suggestion($suggest_id, $review_fields, $user_id)
    {
        $review_fields['user_id'] = $user_id;

        if ($review_fields['status'] === 'REJECTED') {
            return $this->reject_suggestion($suggest_id, $review_fields);
        }

        return $this->approve_suggestion($suggest_id, $review_fields);
    }

    /**
     * Reject a clinic suggestion
     *
     * @param int   $suggest_id
     * @param array $review_fields
     * @return array|WP_Error
     */
    private function reject_suggestion($suggest_id, $review_fields)
    {
        $updated = $this->database->aiclinic_update_clinic_suggestion_status(
            $suggest_id,
            $review_fields
        );

        if ($updated === false) {
            return new WP_Error(
                'update_failed',
                'Failed to reject suggestion',
                ['status' => 500]
            );
        }

        return [
            'suggest_id' => $suggest_id,
            'status'     => 'REJECTED',
        ];
    }

    /**
     * Approve a clinic suggestion
     *
     * @param int   $suggest_id
     * @param array $review_fields
     * @return array|WP_Error
     */
    private function approve_suggestion($suggest_id, $review_fields)
    {
        $suggestion = $this->database->aiclinic_get_clinic_suggestion_by_id($suggest_id);
        if (empty($suggestion)) {
            return new WP_Error(
                'suggestion_not_found',
                'Suggestion not found',
                ['status' => 404]
            );
        }

        $suggested_fields = $suggestion['suggested_fields'] ?? [];
        $suggestion_type  = $suggestion['suggestion_type'] ?? '';
        $clinic_id        = absint($suggestion['clinic_id'] ?? 0);
        $suggested_by     = $suggestion['suggested_by'] ?? 'OTHER';

        $verify_status = ($suggested_by === 'OWNER') ? 'OWNER_VERIFIED' : 'ADMIN_VERIFIED';

        if ($suggestion_type === 'CREATE' && $clinic_id === 0) {
            $result = $this->create_clinic_from_suggestion($suggested_fields, $verify_status);
            if (is_wp_error($result)) {
                return $result;
            }
        } elseif ($suggestion_type === 'EDIT' && $clinic_id > 0) {
            $result = $this->update_clinic_from_suggestion($clinic_id, $suggested_fields, $verify_status);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        $updated = $this->database->aiclinic_update_clinic_suggestion_status(
            $suggest_id,
            $review_fields
        );
        if ($updated === false) {
            return new WP_Error(
                'update_failed',
                'Failed to update suggestion status',
                ['status' => 500]
            );
        }

        return [
            'suggest_id' => $suggest_id,
            'status'     => 'APPROVED',
        ];
    }

    /**
     * Create a new clinic from suggestion
     *
     * @param array  $suggested_fields
     * @param string $verify_status
     * @return int|WP_Error New clinic ID or error
     */
    private function create_clinic_from_suggestion($suggested_fields, $verify_status)
    {
        $specialties = array_key_exists('specialties', $suggested_fields)
            ? $suggested_fields['specialties']
            : null;

        $clinic_data = array_merge(
            array_diff_key($suggested_fields, ['specialties' => true]),
            [
                'verify_status' => $verify_status,
                'is_active'     => 'ACTIVE',
            ]
        );

        $new_clinic_id = $this->database->aiclinic_insert_clinic($clinic_data);
        if ($new_clinic_id === false) {
            return new WP_Error(
                'insert_failed',
                'Failed to create clinic',
                ['status' => 500]
            );
        }

        $tag_result = $this->clinic_service->sync_clinic_specialties(
            $new_clinic_id,
            $specialties
        );
        if (is_wp_error($tag_result)) {
            return $tag_result;
        }

        return $new_clinic_id;
    }

    /**
     * Update existing clinic from suggestion
     *
     * @param int    $clinic_id
     * @param array  $suggested_fields
     * @param string $verify_status
     * @return bool|WP_Error
     */
    private function update_clinic_from_suggestion($clinic_id, $suggested_fields, $verify_status)
    {
        $specialties = array_key_exists('specialties', $suggested_fields)
            ? $suggested_fields['specialties']
            : null;

        $update_fields = array_merge(
            array_diff_key($suggested_fields, ['specialties' => true]),
            [
                'verify_status' => $verify_status,
            ]
        );

        $updated = $this->database->aiclinic_update_clinic(
            $clinic_id,
            $update_fields
        );

        if ($updated === false) {
            return new WP_Error(
                'update_failed',
                'Failed to update clinic',
                ['status' => 500]
            );
        }

        $tag_result = $this->clinic_service->sync_clinic_specialties(
            $clinic_id,
            $specialties
        );
        if (is_wp_error($tag_result)) {
            return $tag_result;
        }

        return true;
    }
}
