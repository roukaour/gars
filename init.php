<?php

/**
 * This file initializes GARS by setting certain PHP settings to known values
 * regardless of which server is running. For instance, "magic quotes" are
 * disabled and $_SERVER variables are set.
 */

# Include configuration data
require_once 'config.php';

# Define BASE_PATH to allow consistent file access throughout GARS
define('BASE_PATH', dirname(__FILE__) . '/');

# Set up PHP properly
fix_magic_quotes();
fix_remote_addr();
fix_server_vars();

/**
 * Include the file containing the named class.
 */
function use_class($class) {
	require_once BASE_PATH . 'classes/' . $class . '.php';
}

/**
 * Returns the number of bytes from an .ini-specified quantity, like "7M".
 */
function ini_int($string) {
	$string = trim($string);
	$value = (int)substr($string, 0, -1);
	$order = strtoupper(substr($string, -1));
	switch ($order) {
	case 'P':
		$value *= 1024;
	case 'T':
		$value *= 1024;
	case 'G':
		$value *= 1024;
	case 'M':
		$value *= 1024;
	case 'K':
		$value *= 1024;
	}
	return $value;
}

/**
 * Make sure PHP's "magic quotes" feature is inactive.
 */
function fix_magic_quotes() {
	set_magic_quotes_runtime(0);
	@ini_set('magic_quotes_sybase', 0);
	if (!function_exists('get_magic_quotes_gpc') || !get_magic_quotes_gpc())
		return;
	$quotes_sybase = strtolower(ini_get('magic_quotes_sybase'));
	$unescape_function = (empty($quotes_sybase) || $quotes_sybase === 'off') ? 'stripslashes($value)' : 'str_replace("\'\'","\'",$value)';
	$stripslashes_deep = create_function('&$value, $fn', '
		if (is_string($value)) {
			$value = ' . $unescape_function . ';
		}
		else if (is_array($value)) {
			foreach ($value as &$v) $fn($v, $fn);
		}
	');
	$stripslashes_deep($_POST, $stripslashes_deep);
	$stripslashes_deep($_GET, $stripslashes_deep);
	$stripslashes_deep($_COOKIE, $stripslashes_deep);
	$stripslashes_deep($_REQUEST, $stripslashes_deep);
}

/**
 * Make sure $_SERVER['REMOTE_ADDR'] is set.
 */
function fix_remote_addr() {
	if (!empty($_SERVER['REMOTE_ADDR'])) {
		return;
	}
	if (isset($_SERVER['HTTP_CLIENT_IP'])) {
		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CLIENT_IP'];
	}
	elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
	}
	else {
		$_SERVER['REMOTE_ADDR'] = 'unknown IP';
	}
}

/**
 * Standardize $_SERVER variables across different servers (Apache, IIS, etc).
 * (Adapted from wp-includes/load.php in Wordpress 3.2.)
 */
function fix_server_vars() {
	$default_server_values = array(
		'SERVER_SOFTWARE' => '',
		'REQUEST_URI' => '',
	);
	$_SERVER = array_merge($default_server_values, $_SERVER);
	# Fix for IIS when running with PHP ISAPI
	if (empty($_SERVER['REQUEST_URI']) || (php_sapi_name() != 'cgi-fcgi' && preg_match('/^Microsoft-IIS\//', $_SERVER['SERVER_SOFTWARE']))) {
		# IIS Mod-Rewrite
		if (isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
		}
		# IIS Isapi_Rewrite
		else if (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_REWRITE_URL'];
		}
		else {
			# Use ORIG_PATH_INFO if there is no PATH_INFO
			if (!isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO']))
				$_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
			# Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
			if (isset($_SERVER['PATH_INFO'])) {
				if ($_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'])
					$_SERVER['REQUEST_URI'] = $_SERVER['PATH_INFO'];
				else
					$_SERVER['REQUEST_URI'] = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
			}
			# Append the query string if it exists and isn't null
			if (! empty($_SERVER['QUERY_STRING'])) {
				$_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
			}
		}
	}
	# Fix for PHP as CGI hosts that set SCRIPT_FILENAME to something ending in php.cgi for all requests
	if (isset($_SERVER['SCRIPT_FILENAME']) && (strpos($_SERVER['SCRIPT_FILENAME'], 'php.cgi') == strlen($_SERVER['SCRIPT_FILENAME']) - 7))
		$_SERVER['SCRIPT_FILENAME'] = $_SERVER['PATH_TRANSLATED'];
	# Fix for Dreamhost and other PHP as CGI hosts
	if (strpos($_SERVER['SCRIPT_NAME'], 'php.cgi') !== false)
		unset($_SERVER['PATH_INFO']);
	# Fix empty PHP_SELF
	if (empty($_SERVER['PHP_SELF']))
		$_SERVER['PHP_SELF'] = preg_replace('/(\?.*)?$/', '', $_SERVER['REQUEST_URI']);
}

?>