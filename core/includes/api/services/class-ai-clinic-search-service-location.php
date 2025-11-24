<?php

if (!defined('ABSPATH')) exit;

/**
 * Class Ai_Clinic_Service_Location
 *
 * Handle loading and querying province/district data.
 *
 * @package AICLINIC
 */
class Ai_Clinic_Service_Location
{
    private $data_file;

    public function __construct()
    {
        $this->data_file = CLINIC_SEARCH_PLUGIN_DIR . 'schemas/location.json';
    }

    /**
     * Load and parse JSON data.
     */
    private function load_data()
    {
        $cache_key = 'ai_clinic_location_data';
        $data = get_transient($cache_key);
        if ($data !== false) {
            return $data;
        }

        if (!file_exists($this->data_file)) {
            return null;
        }

        $json = file_get_contents($this->data_file);
        $data = json_decode($json, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        set_transient($cache_key, $data, DAY_IN_SECONDS);
        return $data;
    }

    /**
     * Get a list of all provinces/cities.
     */
    public function get_all_provinces($search = '')
    {
        $data = $this->load_data();
        if (!$data) {
            return [];
        }

        $provinces = array_map(function ($p) {
            return [
                'code'      => $p['code'],
                'name'      => $p['name'],
                'type'      => $p['type'],
                'isCentral' => $p['isCentral'] ?? false
            ];
        }, $data);

        if (!empty($search)) {
            $search = mb_strtolower($search);
            $provinces = array_filter($provinces, function ($province) use ($search) {
                return mb_strpos(mb_strtolower($province['name']), $search) !== false ||
                    mb_strpos(mb_strtolower($province['slug']), $search) !== false;
            });
            $provinces = array_values($provinces);
        }

        return $provinces;
    }

    /**
     * Get list of wards (district/ward) by province code.
     */
    public function get_districts_by_province($province_code, $search = '')
    {
        $data = $this->load_data();
        if (!$data) {
            return [];
        }

        foreach ($data as $province) {
            if ($province['code'] === $province_code) {
                $wards = $province['wards'] ?? [];

                $wards = array_map(function ($w) {
                    return [
                        'code'   => $w['code'],
                        'name'   => $w['name'],
                        'type'   => $w['type']
                    ];
                }, $wards);

                if (!empty($search)) {
                    $search = mb_strtolower($search);
                    $wards = array_filter($wards, function ($w) use ($search) {
                        return mb_strpos(mb_strtolower($w['name']), $search) !== false ||
                            mb_strpos(mb_strtolower($w['slug']), $search) !== false;
                    });
                    $wards = array_values($wards);
                }

                return $wards;
            }
        }

        return [];
    }

    /**
     * Get province name by province code.
     *
     * @param string|int $province_code
     * @return string|null
     */
    public function get_province_name_by_code($province_code)
    {
        $data = $this->load_data();
        if (!$data) {
            return null;
        }

        foreach ($data as $province) {
            if ($province['code'] === $province_code) {
                return $province['name'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get district/ward name by its code within a province.
     *
     * @param string|int $province_code
     * @param string|int $district_code
     * @return string|null
     */
    public function get_district_name_by_code($province_code, $district_code)
    {
        $data = $this->load_data();
        if (!$data) {
            return null;
        }

        foreach ($data as $province) {
            if ($province['code'] === $province_code) {

                if (empty($province['wards'])) {
                    return null;
                }

                foreach ($province['wards'] as $ward) {
                    if ($ward['code'] === $district_code) {
                        return $ward['name'] ?? null;
                    }
                }

                return null;
            }
        }

        return null;
    }
}
