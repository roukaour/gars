<?php

use_class('GARS');

/**
 * This class logs significant actions taken by GARS.
 */
class Logger {
	/**
	 * Logs a given message along with a timestamp. Return success.
	 */
	public function log($message) {
		$logfile = BASE_PATH . 'logs/gars-' . date('Y-W') . '.log';
		$line = date('[Y-m-d h:i:s]') . ' ' . $message . "\n";
		return error_log($line, 3, $logfile);
	}
	
	/**
	 * Logs a given message along with a timestamp and the username of
	 * the user currently logged into GARS. Return success.
	 */
	public function log_action($message) {
		$gars = GARS::get_instance();
		$username = $gars->get_username();
		return $this->log('<' . $username . '> ' . $message);
	}
}

?>