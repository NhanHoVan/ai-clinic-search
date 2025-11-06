<?php

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

$helpers_path = CLINIC_SEARCH_PLUGIN_DIR . 'core/includes/helpers/';

require_once $helpers_path . 'date-helper.php';
require_once $helpers_path . 'image-helper.php';
require_once $helpers_path . 'phone-number-helper.php';
require_once $helpers_path . 'database-helper.php';
require_once $helpers_path . 'config-helper.php';
require_once $helpers_path . 'enum-helper.php';
require_once $helpers_path . 'multilingual-helper.php';
require_once $helpers_path . 'cookie-helper.php';

use AICLINIC\Core\Includes\Helpers\Date_Helper;
use AICLINIC\Core\Includes\Helpers\Image_Helper;
use AICLINIC\Core\Includes\Helpers\Phone_Number_Helper;
use AICLINIC\Core\Includes\Helpers\Database_Helper;
use AICLINIC\Core\Includes\Helpers\Config_Helper;
use AICLINIC\Core\Includes\Helpers\Multilingual_Helper;
use AICLINIC\Core\Includes\Helpers\Cookie_Helper;
use AICLINIC\Core\Includes\Helpers\{Status_Type_Enum, Pricing_Plan_Enum, Action_Type_Enum, Import_Export_Status_Enum, Mode_Generator_Enum};

/**
 * Class Ai_Clinic_Search_Helpers
 *
 * This class contains repetitive functions that
 * are used globally within the plugin.
 *
 * @package		AICLINIC
 * @subpackage	Classes/Ai_Clinic_Search_Helpers
 * @author		Van Nhan
 * @since		1.0.0
 * 
 * @property Helpers\Date_Helper                            $date
 * @property Helpers\Image_Helper                           $image
 * @property Helpers\Phone_Number_Helper                    $phone_number
 * @property Helpers\Database_Helper                    	$database
 * @property Helpers\Config_Helper                    		$config
 * @property Helpers\Multilingual_Helper                    $multilingual
 * @property Helpers\Cookie_Helper                          $cookie
 */
class Ai_Clinic_Search_Helpers
{

    /**
     * The container for helper instances.
     *
     * @var array
     */
    private $container;

    public function __construct()
    {
        $this->container = [
            'date'          => new Date_Helper(),
            'image'         => new Image_Helper(),
            'phone_number'  => new Phone_Number_Helper(),
            'database'      => new Database_Helper(),
            'config'          => new Config_Helper(),
            'multilingual'  => new Multilingual_Helper(),
            'cookie'        => new Cookie_Helper(),
        ];
    }

    /**
     * ######################
     * ###
     * #### CALLABLE FUNCTIONS
     * ###
     * ######################
     */

    /**
     * Magic getter for getting helper classes.
     *
     * @param string $helper The helper to get.
     *
     * @return mixed The helper class.
     */
    public function __get($helper)
    {
        return $this->container[$helper] ?? null;
    }

    /**
     * Magic isset for ensuring helper exists.
     *
     * @param string $helper The helper to get.
     *
     * @return bool Whether the helper exists.
     */
    public function __isset($helper)
    {
        return isset($this->container[$helper]);
    }

    /** Get enum value. */
    public function getStatusActive(): string
    {
        return Status_Type_Enum::ACTIVE->value;
    }

    public function getStatusInactive(): string
    {
        return Status_Type_Enum::INACTIVE->value;
    }

    public function getStatusUnpaid(): string
    {
        return Status_Type_Enum::UNPAID->value;
    }

    public function getPricingBasic(): string
    {
        return Pricing_Plan_Enum::BASIC->value;
    }

    public function getPricingGold(): string
    {
        return Pricing_Plan_Enum::GOLD->value;
    }

    public function getActionImport(): string
    {
        return Action_Type_Enum::IMPORT->value;
    }

    public function getActionExport(): string
    {
        return Action_Type_Enum::EXPORT->value;
    }

    public function getImportStatusPending(): string
    {
        return Import_Export_Status_Enum::PENDING->value;
    }

    public function getImportStatusCompleted(): string
    {
        return Import_Export_Status_Enum::COMPLETED->value;
    }

    public function getImportStatusFailed(): string
    {
        return Import_Export_Status_Enum::FAILED->value;
    }

    public function getModeImmediate(): string
    {
        return Mode_Generator_Enum::IMMEDIATE->value;
    }

    public function getModeQueue(): string
    {
        return Mode_Generator_Enum::QUEUE->value;
    }
}
