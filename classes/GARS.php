<?php

use_class('Bridge');
use_class('Credentials_Manager');
use_class('Logger');

/**
 * This class handles a single GARS session. It owns the database and credentials
 * manager used by GARS.
 */
class GARS {
	# The singleton instance of GARS
	private static $gars;
	
	# The bridge to the database
	private $bridge;
	# The credentials manager
	private $creds;
	
	/**
	 * Returns the singleton instance of GARS.
	 */
	public static function get_instance() {
		if (!self::$gars) {
			self::$gars = new GARS();
		}
		return self::$gars;
	}
	
	/**
	 * Constructor; starts new PHP session.
	 */
	private function __construct() {
		$this->bridge = new Bridge();
		$this->creds = new Credentials_Manager();
		session_start();
	}
	
	/**
	 * Returns the bridge used by this GARS instance.
	 */
	public function get_bridge() {
		return $this->bridge;
	}
	
	/**
	 * Returns the database used by this GARS instance.
	 */
	public function get_database() {
		return $this->bridge->get_database();
	}
	
	/**
	 * Returns the logger used by this GARS instance.
	 */
	public function get_logger() {
		return $this->bridge->get_logger();
	}
	
	/**
	 * Returns the credentials manager used by this GARS instance.
	 */
	public function get_credentials_manager() {
		return $this->creds;
	}
	
	/**
	 * Returns whether this session is logged in.
	 */
	public function is_logged_in() {
		return $_SESSION['logged_in'];
	}
	
	/**
	 * Returns the username recorded by this session.
	 */
	public function get_username() {
		return $_SESSION['username'];
	}
	
	/**
	 * Returns the ID recorded by this session.
	 */
	public function get_user_id() {
		return $_SESSION['user_id'];
	}
	
	/**
	 * Returns the role recorded by this session.
	 */
	public function get_role() {
		return $_SESSION['role'];
	}
	
	/**
	 * Attempts to log in with the given username and password.
	 */
	public function login($username, $password) {
		$login_data = $this->bridge->login_data($username);
		$logger = $this->bridge->get_logger();
		if (is_null($login_data)) {
			$logger->log('Failed login attempt by nonexistent user ' .
				$username . ' from ' . $_SERVER['REMOTE_ADDR'] . '.');
			return false;
		}
		$correct = $this->creds->is_correct($password, $login_data['salt'], $login_data['hash']);
		if ($correct) {
			$_SESSION['logged_in'] = true;
			$_SESSION['username'] = $username;
			$_SESSION['user_id'] = $login_data['id'];
			$_SESSION['role'] = $login_data['role'];
			$logger->log_action('Logged in from ' . $_SERVER['REMOTE_ADDR'] . '.');
		}
		else {
			$logger->log('Failed login attempt by ' . $username .
				' from ' . $_SERVER['REMOTE_ADDR'] . '.');
			$_SESSION['logged_in'] = false;
		}
		return $_SESSION['logged_in'];
	}
	
	/**
	 * Logs out and destroys the current session.
	 */
	public function logout() {
		$logger = $this->bridge->get_logger();
		$logger->log_action('Logged out from ' . $_SERVER['REMOTE_ADDR'] . '.');
		$_SESSION = array();
		session_destroy();
	}
}

?>