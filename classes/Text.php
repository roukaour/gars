<?php

/**
 * This class acts contains static methods for formatting text.
 */
class Text {
	/**
	 * Sanitizes a string for HTML output.
	 */
	public static function html_encode($string) {
		return htmlspecialchars($string, ENT_QUOTES|ENT_HTML5, CHARSET);
	}
	
	/**
	 * Escapes the wildcard characters in MySQL LIKE strings, % and _.
	 */
	public static function mysql_like_escape($string, $escape='\\') {
		$from = array($escape, '_', '%');
		$to = array($escape . $escape, $escape . '_', $escape . '%');
		return str_replace($from, $to, $string);
	}
	
	/**
	 * Returns an integer as an ordinal.
	 */
	public static function ordinal($n) {
		$n = (int)$n;
		$ones = $n % 10;
		$tens = (int)(($n % 100) / 10);
		switch ($ones) {
		case 1:
			if ($tens != 1) {
				return "${n}st";
			}
		case 2:
			if ($tens != 1) {
				return "${n}nd";
			}
		case 3:
			if ($tens != 1) {
				return "${n}rd";
			}
		default:
			return "${n}th";
		}
	}
	
	/**
	 * Returns a 10- or 11-digit phone number.
	 */
	public static function format_phone($phone) {
		return trim(preg_replace('/^(\d?)(\d{3})(\d{3})(\d{4})$/',
			'$1 ($2) $3-$4', $phone));
	}
	
	/**
	 * Returns a more descriptive gender.
	 */
	public static function format_gender($gender) {
		switch ($gender) {
		case 'M':
		case 'm':
			return 'Male';
		case 'F':
		case 'f':
			return 'Female';
		default:
			return $gender;
		}
	}
	
	/**
	 * Returns an HTML list of field-value pairs, parsed from semicolon-
	 * delimited pairs.
	 */
	public static function format_other_info($info) {
		$items = array();
		$pairs = explode('; ', $info);
		foreach ($pairs as $pair) {
			list($field, $value) = explode('=', $pair, 2);
			$field = trim(self::html_encode($field));
			$value = trim(self::html_encode($value));
			if (empty($value)) {
				$value = '<i>Not specified</i>';
			}
			$items[] = sprintf(
				'<li><b>%s:</b> %s</li>',
				$field, $value
			);
		}
		if (empty($items)) {
			return '';
		}
		return '<ul>' . implode("\n", $items) . '</ul>';
	}
}

?>