<?php

namespace AICLINIC\Core\Includes\Helpers;

/**
 * A helper object for Multilingual matters.
 */
class Multilingual_Helper
{

	/**
	 * Get the current language code of the website.
	 *
	 * This function attempts to detect the current language code (2-letter code)
	 * based on active multilingual plugins like Polylang or WPML.
	 * If no multilingual plugin is active, it falls back to the site's locale setting.
	 * Only 'vi' (Vietnamese) and 'en' (English) are considered valid codes.
	 * Any other detected language code defaults to 'en'.
	 *
	 * @return string Returns a 2-letter language code ('vi' or 'en').
	 */
	public function aiclinic_get_current_language_code()
	{
		$lang = '';

		if (function_exists('pll_current_language')) {
			$lang = pll_current_language('slug');
		} elseif (defined('ICL_SITEPRESS_VERSION')) {
			$lang = apply_filters('wpml_current_language', NULL);
		} else {
			$locale = get_locale();
			$lang = substr($locale, 0, 2);
		}

		$lang = strtolower($lang);
		$allowed = ['vi', 'en'];
		if (!in_array($lang, $allowed, true)) {
			$lang = 'en';
		}

		return $lang;
	}
}
