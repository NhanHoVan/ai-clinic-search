<?php

namespace AICLINIC\Core\Includes\Helpers;

/**
 * A helper object for Phone number.
 */
class Phone_Number_Helper
{

	/** 
	 * Check is valid Vietnam phone number.
	 * 
	 * @access	public
	 * @since	1.0.0
	 * @param   string message
	 * @return	bool
	 */
	private function aiclinic_is_valid_vietnam_phone_mumber($phoneNumber)
	{
		$vietnamPhoneRegex = '/^(0[1-9]|84[1-9])[0-9]{8}$/';
		return preg_match($vietnamPhoneRegex, $phoneNumber);
	}

	/**
	 * Check VN phone number and save phone number to cookie
	 * 
	 * @access	public
	 * @since	1.0.0
	 * @param   string message
	 * @return	bool
	 */
	public function aiclinic_is_vn_phone_number($message)
	{
		$phoneRegex = '/\b\d{10}\b/';
		preg_match_all($phoneRegex, $message, $matches);

		if (!empty($matches[0])) {
			$isValidPhoneNumber = false;

			foreach ($matches[0] as $phoneNumber) {
				if ($this->aiclinic_is_valid_vietnam_phone_mumber($phoneNumber)) {
					$isValidPhoneNumber = true;
					break;
				}
			}
			return $isValidPhoneNumber;
		} else {
			return false;
		}
	}
}
