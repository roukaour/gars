<?php

use_class('GARS');

/**
 * This class handles a single GARS page. It outputs HTML code common to all
 * pages, like the header and footer, and keeps track of which users are allowed
 * to access the page.
 */
class Page {
	# The page's <title> and heading
	private $title;
	
	/**
	 * Constructor; redirects to login page if unauthorized.
	 */
	public function __construct($title, $is_login=false) {
		$this->title = $title;
		$this->secured = $secured;
		$gars = GARS::get_instance();
		if (!$is_login && !$gars->is_logged_in()) {
			$logger = $gars->get_logger();
			$logger->log('Denied access to "' . $this->title .
				'" from ' . $_SERVER['REMOTE_ADDR'] . '.');
			header('Location: ' . BASE_URL . 'index.php?denied');
			die();
		}
	}
	
	/**
	 * Halts GARS if the current user is not an allowed role.
	 */
	public function allow_only() {
		$gars = GARS::get_instance();
		$role = $gars->get_role();
		$allowed_roles = func_get_args();
		if (!in_array($role, $allowed_roles)) {
			$logger = $gars->get_logger();
			$logger->log_action('Denied access to "' . $this->title . '".');
			$this->begin();
			echo '<p class="error"><b>Access denied!</b></p>';
			$this->end();
			die();
		}
	}
	
	/**
	 * Returns the title of this Page.
	 */
	public function get_title() {
		return $this->title;
	}
	
	/**
	 * Outputs beginning HTML code, like the <head> and header.
	 */
	public function begin($use_app_filters=false) {
		include BASE_PATH . 'includes/begin.php';
	}
	
	/**
	 * Outputs ending HTML code, like the footer and closing tags.
	 */
	public function end() {
		include BASE_PATH . 'includes/end.php';
	}
}

?>