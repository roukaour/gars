<?php

use_class('GARS');
use_class('Database');
use_class('Data_Parser');
use_class('Assignment_Problem');
use_class('Text');

/**
 * This class acts as a bridge between the MySQL database, which stores data,
 * and the individual pages, which request and display data. It is responsible
 * for constructing specific queries to the database and returning the results.
 */
class Bridge {
	# The MySQL database backing this Bridge
	private $database;
	# The logger used to track significant actions
	private $logger;
	
	/**
	 * Constructor; initializes the database.
	 */
	public function __construct() {
		$this->database = new Database();
		$this->database->connect();
		$this->database->create_tables();
		$this->logger = new Logger();
	}
	
	/**
	 * Returns the MySQL database backing this Bridge.
	 * Called from the GARS class.
	 */
	public function get_database() {
		return $this->database;
	}
	
	/**
	 * Returns the Logger used by this Bridge.
	 * Called from the GARS class.
	 */
	public function get_logger() {
		return $this->logger;
	}
	
	/**
	 * Returns the data needed to log in the given user.
	 * Called from the GARS class.
	 */
	public function login_data($username) {
		$query = sprintf(
			"SELECT id, pass_hash AS hash, pass_salt AS salt, role
			FROM users WHERE username='%s' LIMIT 1",
			mysql_real_escape_string($username)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return null;
		}
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Returns all applications pending review by the current user, or an
	 * HTML message if an error occurred.
	 * Called from home.php.
	 */
	public function get_pending_reviews() {
		$gars = GARS::get_instance();
		$my_id = $gars->get_user_id();
		$query = sprintf(
			"SELECT app_id, email_address FROM reviews
			LEFT JOIN applications ON app_id = id
			WHERE user_id = %d AND rating IS NULL
			ORDER BY date DESC, email_address ASC",
			(int)$my_id
		);
		$result = $this->database->query($query);
		if (!is_resource($result)) {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not get pending applications. MySQL error ' .
				$error . '.'
			);
			return sprintf(
				'<p class="error">Could not get your pending
				applications from the database!<br><br>MySQL
				error %s.</p>',
				Text::html_encode($error)
			);
		}
		return $result;
	}
	
	/**
	 * Returns all applications pending a decision by the current user, or
	 * an HTML message if an error occurred.
	 * Called from home.php.
	 */
	public function get_pending_decisions() {
		$query = "SELECT app_id, email_address, numDesiredReviews, tier,
			COUNT(rating) AS num_reviews FROM reviews
			LEFT JOIN applications ON app_id = id GROUP BY app_id
			HAVING num_reviews >= numDesiredReviews AND
			(tier = -1 OR (tier >= 1.5 AND tier < 2.0))
			ORDER BY date DESC";
		$result = $this->database->query($query);
		if (!is_resource($result)) {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not get pending decisions. MySQL error ' .
				$error . '.');
			return sprintf(
				'<p class="error">Could not get the pending
				decisions from the database!<br><br>MySQL error
				%s.</p>',
				Text::html_encode($error)
			);
		}
		return $result;
	}
	
	/**
	 * Returns a single page of all applications.
	 * Called from applications.php.
	 */
	public function get_applications($page) {
		return $this->get_some_applications($page);
	}
	
	/**
	 * Searches for the given terms in all applications' names and email
	 * addresses. Returns the database result along with a page number.
	 * Called from applications.php.
	 */
	public function search_applications($terms, $page) {
		$terms = trim($terms);
		if ($terms == '') {
			return '<p class="error">Enter a name or email address
				to search for!</p>';
		}
		$terms = Text::mysql_like_escape($terms, '#');
		$terms = preg_replace('/\s+/', '%', $terms);
		$where = sprintf(
			"WHERE CONCAT_WS(' ', first_name, middle_name, last_name,
			email_address) LIKE '%%%s%%' ESCAPE '#'",
			mysql_real_escape_string($terms)
		);
		$response = $this->get_some_applications($page, $where);
		return $response;
	}
	
	/**
	 * Filters the applications table according to the given criteria.
	 * Returns the database result along with a page number.
	 * Called from applications.php.
	 */
	public function filter_applications($filters, $sort, $only_mine, $unreviewed, $page) {
		$clauses = $this->filter_clauses($filters, $sort, $only_mine, $unreviewed);
		if ($clauses == false) {
			return '<p class="error">Invalid filters!</p>';
		}
		$response = $this->get_some_applications($page, $clauses['WHERE'],
			$clauses['HAVING'], $clauses['ORDER BY']);
		return $response;
	}
	
	/**
	 * Returns MySQL WHERE, HAVING, and ORDER BY clauses based on the given
	 * array of filters, or false if the filters are invalid.
	 * Called from the filter_applications method.
	 */
	private function filter_clauses($filters, $sort, $only_mine, $unreviewed) {
		# Parse filters into WHERE and HAVING criteria
		if (empty($filters)) {
			$filters = array();
		}
		if (!is_array($filters)) {
			return false;
		}
		# Limit to 5 filters
		$filters = array_slice($filters, 0, 5);
		$where_criteria = array();
		$having_criteria = array();
		foreach ($filters as $filter) {
			if (!is_array($filter)) {
				return false;
			}
			$column = $filter['c'];
			$relation = $filter['r'];
			$value = trim($filter['v']);
			$having = false;
			if ($column == '' || $value == '') {
				continue;
			}
			# Handle some columns specially
			switch ($column) {
			# Match with the name, not the ID
			case 'citizenship':
				$column = 'citizenship_country_name';
				$having = true;
				break;
			# Match with the abbreviation, not the ID
			case 'research_area':
				$column = 'research_area_abbr';
				$having = true;
				break;
			# Filtering by 'male' should match 'M'
			case 'gender':
				$value = strtoupper(substr($value, 0, 1));
				break;
			# Filtering by 'yes' should match true
			case 'permanent_resident':
				$value = strtolower(substr($value, 0, 1)) == 'y';
				break;
			}
			$criterion = '';
			switch ($relation) {
			# Relation is =
			case 'is':
				$criterion = sprintf(
					"%s = '%s'",
					mysql_real_escape_string($column),
					mysql_real_escape_string($value)
				);
				break;
			# Relation is LIKE
			case 'in':
				$value = Text::mysql_like_escape($value, '#');
				$value = preg_replace('/\s+/', '%', $value);
				$criterion = sprintf(
					"%s LIKE '%%%s%%' ESCAPE '#'",
					mysql_real_escape_string($column),
					mysql_real_escape_string($value)
				);
				break;
			# Relation is >=
			case 'ge':
				$criterion = sprintf(
					"%s >= %f",
					mysql_real_escape_string($column),
					(double)$value
				);
				break;
			# Relation is <=
			case 'le':
				$criterion = sprintf(
					"%s <= %f",
					mysql_real_escape_string($column),
					(double)$value
				);
				break;
			default:
				return false;
			}
			if ($having) {
				$having_criteria[] = $criterion;
			}
			else {
				$where_criteria[] = $criterion;
			}
		}
		# Only show applications pending review by the current user
		if ($unreviewed) {
			$having_criteria[] = 'NOT reviewed';
		}
		# Only show applications assigned to the current user
		elseif ($only_mine) {
			$having_criteria[] = 'reviewed IS NOT NULL';
		}
		$where = implode(' AND ', $where_criteria);
		if (!empty($where)) {
			$where = 'WHERE ' . $where;
		}
		$having = implode(' AND ', $having_criteria);
		if (!empty($having)) {
			$having = 'HAVING ' . $having;
		}
		# Parse sort columns
		if (empty($sort)) {
			$sort = array();
		}
		if (!is_array($sort)) {
			return false;
		}
		$order = array();
		foreach ($sort as $column) {
			if ($column == '') {
				continue;
			}
			$order[] = mysql_real_escape_string($column) . ' ASC';
		}
		# Limit to 3 sort columns
		$order = array_slice($order, 0, 3);
		if (empty($order)) {
			$order = array('last_name ASC', 'first_name ASC');
		}
		$order_by = 'ORDER BY ' . implode(', ', $order);
		# Return the SELECT clauses
		return array('WHERE' => $where, 'HAVING' => $having,
			'ORDER BY' => $order_by);
	}
	
	/**
	 * Returns a page of the applications that match the given clauses.
	 * The clause is interpreted as MySQL, so be careful what this function
	 * gets called with.
	 * Called from the get_applications, search_applications, and
	 * filter_applications methods.
	 */
	private function get_some_applications($page, $where='', $having='', $order_by='') {
		# Use default order if none was specified
		if (empty($order_by)) {
			$order_by = 'ORDER BY last_name ASC, first_name ASC';
		}
		# Clamp page to appropriate range and find page offset
		$gars = GARS::get_instance();
		$my_id = $gars->get_user_id();
		$query = sprintf(
			"SELECT applications.id,
			research_areas.abbr AS research_area_abbr,
			countries.name AS citizenship_country_name,
			grouped_reviews.ratings, is_reviewed.reviewed
			FROM applications
			LEFT JOIN research_areas ON applications.research_area = research_areas.id
			LEFT JOIN countries ON applications.citizenship = countries.code
			LEFT JOIN (
				SELECT app_id,
				GROUP_CONCAT(rating ORDER BY rating ASC SEPARATOR ', ') AS ratings
				FROM reviews GROUP BY app_id
			) AS grouped_reviews ON applications.id = grouped_reviews.app_id
			LEFT JOIN (
				SELECT app_id, IF(rating IS NULL, false, true) AS reviewed
				FROM reviews WHERE user_id = %d
			) AS is_reviewed ON applications.id = is_reviewed.app_id
			%s %s",
			(int)$my_id, $where, $having
		);
		$result = $this->database->query($query);
		if (!is_resource($result)) {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not get applications. MySQL error ' .
				$error . '.'
			);
			return sprintf(
				'<p class="error">Could not get the applications
				from the database!<br><br>MySQL error %s.</p>',
				Text::html_encode($error)
			);
		}
		$num_rows = mysql_num_rows($result);
		$max_page = ceil($num_rows / APPS_PER_PAGE);
		$page = (int)$page;
		if ($page < 1) {
			$page = 1;
		}
		elseif ($page > $max_page) {
			$page = $max_page;
		}
		$offset = ($page - 1) * APPS_PER_PAGE;
		# Get the data range
		$query = sprintf(
			"SELECT applications.*,
			research_areas.abbr AS research_area_abbr,
			countries.name AS citizenship_country_name,
			grouped_reviews.ratings, is_reviewed.reviewed,
			DATE_FORMAT(submission_date, '%%m/%%d/%%Y') AS submission_date_formatted,
			DATE_FORMAT(uploadDate, '%%h:%%i %%p, %%m/%%d/%%Y') AS uploadDate_formatted
			FROM applications
			LEFT JOIN research_areas ON applications.research_area = research_areas.id
			LEFT JOIN countries ON applications.citizenship = countries.code
			LEFT JOIN (
				SELECT app_id,
				GROUP_CONCAT(rating ORDER BY rating ASC SEPARATOR ', ') AS ratings
				FROM reviews GROUP BY app_id
			) AS grouped_reviews ON applications.id = grouped_reviews.app_id
			LEFT JOIN (
				SELECT app_id, IF(rating IS NULL, false, true) AS reviewed
				FROM reviews WHERE user_id = %d
			) AS is_reviewed ON applications.id = is_reviewed.app_id
			%s %s %s LIMIT %d, %d",
			(int)$my_id, $where, $having, $order_by, $offset, APPS_PER_PAGE
		);
		$result = $this->database->query($query);
		if (!is_resource($result)) {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not get applications. MySQL error ' .
				$error . '.'
			);
			return sprintf(
				'<p class="error">Could not get the applications
				from the database!<br><br>MySQL error %s.</p>',
				Text::html_encode($error)
			);
		}
		return array('result' => $result, 'page' => $page,
			'max' => $max_page);
	}
	
	/**
	 * Returns the URL of the PDF for the given email, or null if it does
	 * not exist.
	 * Called from applications.php and application.php.
	 */
	public function pdf_url($email) {
		$exists = file_exists(BASE_PATH . 'pdfs/' . $email . '.pdf');
		if (!$exists) {
			return null;
		}
		return sprintf('%spdfs/%s.pdf', BASE_URL, urlencode($email));
	}
	
	/**
	 * Returns a single application corresponding to the given email address,
	 * or an HTML message if an error occurred.
	 * Called from application.php.
	 */
	public function get_application($email_address) {
		$query = sprintf(
			"SELECT applications.*,
			research_areas.name AS research_area_name,
			countries.name AS citizenship_country_name,
			DATE_FORMAT(submission_date, '%%m/%%d/%%Y') AS submission_date_formatted,
			DATE_FORMAT(uploadDate, '%%h:%%i %%p, %%m/%%d/%%Y') AS uploadDate_formatted
			FROM applications
			LEFT JOIN research_areas ON applications.research_area = research_areas.id
			LEFT JOIN countries ON applications.citizenship = countries.code
			WHERE email_address='%s' LIMIT 1",
			mysql_real_escape_string($email_address)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">No application exists for
				<b>%s</b>!</p>',
				Text::html_encode($email_address)
			);
		}
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Edits decision-related application data for the given application.
	 * Returns an HTML message describing what occurred.
	 * Called from application.php.
	 */
	public function update_decision_data($email, $desired_reviews, $tier, $summary, $toefl_comments) {
		# Make sure the current user is allowed to edit this data
		$gars = GARS::get_instance();
		if ($gars->get_role() != 'chair') {
			$this->logger->log_action(
				'Not allowed to update decision data for ' .
				$email . '.'
			);
			return '<p class="error">You are not allowed to update
				decision data!</p>';
		}
		# Make sure the application exists
		$query = sprintf(
			"SELECT email_address FROM applications
			WHERE email_address = '%s' LIMIT 1",
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">No application exists for
				<b>%s</b>!</p>',
				Text::html_encode($email)
			);
		}
		# Update the data
		$query = sprintf(
			"UPDATE applications SET numDesiredReviews = %d,
			tier = %f, summary = '%s', TOEFLcomments = '%s'
			WHERE email_address = '%s' LIMIT 1",
			(int)$desired_reviews,
			$tier == '' ? -1.0 : (double)$tier,
			mysql_real_escape_string($summary),
			mysql_real_escape_string($toefl_comments),
			mysql_real_escape_string($email)
		);
		$updated = $this->database->query($query);
		# Return a message describing what occurred
		if ($updated) {
			$this->logger->log_action(
				'Updated decision data for ' . $email . '.'
			);
			return sprintf(
				'<p class="success">Updated the decision data for
				<b>%s</b>!</p>',
				Text::html_encode($email)
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not update decision data for ' . $email .
			'. MySQL error ' . $error . '.'
		);
		return sprintf(
			'<p class="error">Could not update the decision data for
			<b>%s</b>!<br><br>MySQL error %s.</p>',
			Text::html_encode($email), Text::html_encode($error)
		);
	}
	
	/**
	 * Updates SBU-related application data for the given application.
	 * Returns an HTML message describing what occurred.
	 * Called from application.php.
	 */
	public function edit_sbu_data($email, $sbu_id, $sbu_gpa) {
		# Make sure the application exists
		$query = sprintf(
			"SELECT email_address FROM applications
			WHERE email_address = '%s' LIMIT 1",
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">No application exists for
				<b>%s</b>!</p>',
				Text::html_encode($email)
			);
		}
		# Update the data
		$query = sprintf(
			"UPDATE applications SET SBU_ID = '%s', SBU_GPA = %s
			WHERE email_address = '%s' LIMIT 1",
			mysql_real_escape_string($sbu_id),
			$sbu_gpa == '' ? 'NULL' : (double)$sbu_gpa,
			mysql_real_escape_string($email)
		);
		$edited = $this->database->query($query);
		# Return a message describing what occurred
		if ($edited) {
			$this->logger->log_action(
				'Edited SBU data for ' . $email . '.'
			);
			return sprintf(
				'<p class="success">Edited the SBU data for
				<b>%s</b>!</p>',
				Text::html_encode($email)
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not edit SBU data for ' . $email .
			'. MySQL error ' . $error . '.'
		);
		return sprintf(
			'<p class="error">Could not edit the SBU data for
			<b>%s</b>!<br><br>MySQL error %s.</p>',
			Text::html_encode($email),
			Text::html_encode($error)
		);
	}
	
	/**
	 * Returns an array of all reviews for the application with the given
	 * email address.
	 * Called from application.php.
	 */
	public function get_reviews($email) {
		$query = sprintf(
			"SELECT reviews.*, users.username, applications.email_address,
			DATE_FORMAT(date, '%%h:%%i %%p, %%m/%%d/%%Y') AS date_formatted
			FROM reviews
			LEFT JOIN users ON reviews.user_id = users.id
			LEFT JOIN applications ON reviews.app_id = applications.id
			HAVING email_address = '%s' ORDER BY date DESC",
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		# Structure data as an array
		$made = array();
		$pending = array();
		$mine = false;
		$hide = false;
		$gars = GARS::get_instance();
		$my_id = $gars->get_user_id();
		$num_reviews = mysql_num_rows($result);
		for ($i = 0; $i < $num_reviews; ++$i) {
			$review = mysql_fetch_assoc($result);
			$is_made = !is_null($review['rating']);
			$is_mine = $review['user_id'] == $my_id;
			if ($is_mine) {
				$mine = true;
			}
			if ($is_made) {
				$made[] = $review;
			}
			else {
				$pending[] = $review;
				if ($is_mine) {
					$hide = true;
				}
			}
		}
		return array('made' => $made, 'pending' => $pending,
			'mine' => $mine, 'hide' => $hide);
	}
	
	/**
	 * Returns a single review for the given applicant's email and user's
	 * ID, or an HTML message if an error occurred.
	 * Called from review.php.
	 */
	public function get_review($email) {
		$gars = GARS::get_instance();
		$my_id = $gars->get_user_id();
		$query = sprintf(
			"SELECT reviews.*, applications.email_address,
			DATE_FORMAT(date, '%%h:%%i %%p, %%m/%%d/%%Y') AS date_formatted
			FROM reviews
			LEFT JOIN applications ON reviews.app_id = applications.id
			WHERE user_id = %d HAVING email_address = '%s' LIMIT 1",
			(int)$my_id,
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">No application exists for %s,
				or %s was not assigned to you for review!</p>',
				Text::html_encode($email),
				Text::html_encode($email)
			);
		}
		return mysql_fetch_assoc($result);
	}
	
	/**
	 * Enters a review for the given applicant by the given user.
	 * Returns an HTML message describing what occurred.
	 * Called from review.php.
	 */
	public function enter_review($email, $username, $rating, $review) {
		# Make sure the rating is between 0 and 6
		if ($rating == '' || ((double)$rating < 0.0 ||
			(double)$rating > 6.0)) {
			return '<p class="error">Enter a rating between 0 and 6!</p>';
		}
		# Make sure the current user is allowed to enter this review
		$gars = GARS::get_instance();
		$my_username = $gars->get_username();
		if ($username != $my_username && $gars->get_role() != 'chair') {
			$this->logger->log_action(
				'Not allowed to enter review for ' . $email .
				' by ' . $username . '.'
			);
			return sprintf(
				'<p class="error">You are not allowed to edit a
				review by %s!</p>',
				Text::html_encode($username)
			);
		}
		# Make sure the review exists
		$query = sprintf(
			"SELECT reviews.*, users.username, applications.email_address,
			IF(rating IS NULL, false, true) AS reviewed
			FROM reviews
			LEFT JOIN users ON reviews.user_id = users.id
			LEFT JOIN applications ON reviews.app_id = applications.id
			HAVING username = '%s' AND email_address = '%s' LIMIT 1",
			mysql_real_escape_string($username),
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">%s has not been assigned to %s
				for review!</p>',
				Text::html_encode($email),
				Text::html_encode($username)
			);
		}
		$prior_review = mysql_fetch_assoc($result);
		# Save any prior review in the prior_reviews table
		$verbs = array('Entered', 'enter');
		if ($prior_review['reviewed']) {
			$query = sprintf(
				"INSERT INTO prior_reviews VALUES
				(%d, %d, %f, '%s', '%s')",
				(int)$prior_review['user_id'],
				(int)$prior_review['app_id'],
				(double)$prior_review['rating'],
				mysql_real_escape_string($prior_review['review']),
				mysql_real_escape_string($prior_review['date'])
			);
			$saved = $this->database->query($query);
			if (!$saved) {
				$error = $this->database->error();
				$this->logger->log_action(
					'Could not save prior review for ' .
					$email . ' by ' . $username .
					'. MySQL error ' . $error . '.'
				);
				return sprintf(
					'<p class="error">Could not save the prior
					review! The review was not edited!<br>
					<br>MySQL error %s.</p>',
					Text::html_encode($error)
				);
			}
			else {
				$this->logger->log_action(
					'Saved prior review for ' . $email .
					' by ' . $username . '.'
				);
			}
			$verbs = array('Edited', 'edit');
		}
		# Enter or edit the reveiw
		$query = sprintf(
			"UPDATE reviews SET rating = %f, review = '%s', date = NOW()
			WHERE user_id = %d AND app_id = %d LIMIT 1",
			(double)$rating,
			mysql_real_escape_string($review),
			(int)$prior_review['user_id'],
			(int)$prior_review['app_id']
		);
		$entered = $this->database->query($query);
		# Update the average review
		$query = sprintf(
			"UPDATE applications SET avgRating = (SELECT AVG(rating)
			FROM reviews WHERE app_id = %d) WHERE id = %d LIMIT 1",
			(int)$prior_review['app_id'],
			(int)$prior_review['app_id']
		);
		$averaged = $this->database->query($query);
		if ($averaged) {
			$this->logger->log_action(
				'Updated average review for ' . $email . '.'
			);
		}
		else {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not update average review for ' . $email .
				'. MySQL error ' . $error . '.'
			);
		}
		# Return a message describing what occurred
		if ($entered) {
			$this->logger->log_action(
				$verbs[0] . ' review for ' . $email . ' by ' .
				$username . '.'
			);
			if ($username == $my_username) {
				return sprintf(
					'<p class="success">%s your review!</p>',
					$verbs[0]
				);
			}
			return sprintf(
				'<p class="success">%s the review by <b>%s</b>!</p>',
				$verbs[0],
				Text::html_encode($username)
			);
		}
		$error = $this->database->error();
		$this->logger->log_action('Could not ' . $verbs[1] . ' review for ' .
			$email . ' by ' . $username . '. MySQL error ' . $error . '.');
		if ($username == $my_username) {
			return sprintf(
				'<p class="error">Could not %s your review!<br>
				<br>MySQL error %s.</p>',
				$verbs[1],
				Text::html_encode($error)
			);
		}
		return sprintf(
			'<p class="error">Could not %s the review by <b>%s</b>!<br>
			<br>MySQL error %s.</p>',
			$verbs[1],
			Text::html_encode($username),
			Text::html_encode($error)
		);
	}
	
	/**
	 * Assigns the given application to the given user for review.
	 * Returns an HTML message describing what occurred.
	 * Called from assignments.php.
	 */
	public function assign_review($username, $email) {
		# Make sure the current user is allowed to assign reviews
		$gars = GARS::get_instance();
		if ($gars->get_role() != 'chair') {
			$this->logger->log_action('Not allowed to assign ' .
				$email . ' to ' . $username . ' for review.');
			return '<p class="error">You are not allowed to edit
				review assignments!</p>';
		}
		# Make sure user and application were entered
		if ($username == '') {
			return '<p class="error">Enter a reviewer\'s username!</p>';
		}
		if ($email == '') {
			return '<p class="error">Enter an applicant\'s email!</p>';
		}
		# Make sure the user and application both exist
		$query = sprintf(
			"SELECT users.id AS user_id, users.username,
			applications.id AS app_id, applications.email_address
			FROM users, applications
			WHERE username = '%s' AND email_address = '%s' LIMIT 1",
			mysql_real_escape_string($username),
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">No user named <b>%s</b> exists,
				and/or no application for <b>%s</b> exists!</p>',
				Text::html_encode($username),
				Text::html_encode($email)
			);
		}
		$user_id = mysql_result($result, 0, 'user_id');
		$app_id = mysql_result($result, 0, 'app_id');
		# Make sure the assignment does not already exist
		$query = sprintf(
			"SELECT rating FROM reviews WHERE user_id = %d AND
			app_id = %d LIMIT 1",
			(int)$user_id, (int)$app_id
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) == 1) {
			return sprintf(
				'<p class="error"><b>%s</b> is already assigned
				to review <b>%s</b>!</p>',
				Text::html_encode($username),
				Text::html_encode($email)
			);
		}
		# Update the database
		$query = sprintf(
			"INSERT INTO reviews (user_id, app_id, date) VALUES
			(%d, %d, NOW())",
			(int)$user_id, (int)$app_id
		);
		$assigned = $this->database->query($query);
		$query = sprintf(
			"UPDATE users
			SET num_assigned_reviews = num_assigned_reviews + 1
			WHERE id = %d LIMIT 1",
			(int)$user_id
		);
		$incremented = $this->database->query($query);
		if ($incremented) {
			$this->logger->log_action(
				'Incremented num_assigned_reviews for ' .
				$username . '.'
			);
		}
		else {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not increment num_assigned_reviews for ' .
				$username . '. MySQL error ' . $error . '.'
			);
		}
		# Return a message describing what occurred
		if ($assigned) {
			$this->logger->log_action(
				'Assigned ' . $email . ' to ' . $username .
				' for review.'
			);
			return sprintf(
				'<p class="success">Assigned <b>%s</b> to
				<b>%s</b> for review!</p>',
				Text::html_encode($email),
				Text::html_encode($username)
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not assign ' . $email . ' to ' . $username .
			' for review. MySQL error ' . $error . '.'
		);
		return sprintf(
			'<p class="error">Could not assign <b>%s</b> to <b>%s</b>
			for review!<br><br>MySQL error %s.</p>',
			Text::html_encode($email),
			Text::html_encode($username),
			Text::html_encode($error)
		);
	}
	
	/**
	 * Unssigns the given application from the given user for review.
	 * Returns an HTML message describing what occurred.
	 * Called from assignments.php.
	 */
	public function unassign_review($username, $email) {
		# Make sure the current user is allowed to unassign reviews
		$gars = GARS::get_instance();
		if ($gars->get_role() != 'chair') {
			$this->logger->log_action('Not allowed to unassign ' .
				$email . ' from ' . $username . ' for review.');
			return '<p class="error">You are not allowed to edit
				review assignments!</p>';
		}
		# Make sure user and application were entered
		if ($username == '') {
			return '<p class="error">Enter a reviewer\'s username!</p>';
		}
		if ($email == '') {
			return '<p class="error">Enter an applicant\'s email!</p>';
		}
		# Make sure the user and application both exist
		$query = sprintf(
			"SELECT users.id AS user_id, users.username,
			applications.id AS app_id, applications.email_address
			FROM users, applications
			WHERE username = '%s' AND email_address = '%s' LIMIT 1",
			mysql_real_escape_string($username),
			mysql_real_escape_string($email)
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error">No user named <b>%s</b> exists,
				and/or no application for <b>%s</b> exists!</p>',
				Text::html_encode($username),
				Text::html_encode($email)
			);
		}
		$user_id = mysql_result($result, 0, 'user_id');
		$app_id = mysql_result($result, 0, 'app_id');
		# Make sure the assignment already exists
		$query = sprintf(
			"SELECT rating FROM reviews WHERE user_id = %d AND
			app_id = %d LIMIT 1",
			(int)$user_id, (int)$app_id
		);
		$result = $this->database->query($query);
		if (mysql_num_rows($result) < 1) {
			return sprintf(
				'<p class="error"><b>%s</b> is not assigned to
				review <b>%s</b>!</p>',
				Text::html_encode($username),
				Text::html_encode($email)
			);
		}
		# Make sure the assignment has not already been completed
		$rating = mysql_result($result, 0, 'rating');
		if (is_string($rating)) {
			return sprintf(
				'<p class="error"><b>%s</b> has already entered
				their review for <b>%s</b>!</p>',
				Text::html_encode($username),
				Text::html_encode($email)
			);
		}
		# Update the database
		$query = sprintf(
			"DELETE FROM reviews WHERE user_id = %d AND app_id = %d LIMIT 1",
			(int)$user_id, (int)$app_id
		);
		$unassigned = $this->database->query($query);
		$query = sprintf(
			"UPDATE users
			SET num_assigned_reviews = num_assigned_reviews - 1
			WHERE id = %d LIMIT 1",
			(int)$user_id
		);
		$decremented = $this->database->query($query);
		if ($decremented) {
			$this->logger->log_action(
				'Decremented num_assigned_reviews for ' .
				$username . '.'
			);
		}
		else {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not decrement num_assigned_reviews for ' .
				$username . '. MySQL error ' . $error . '.'
			);
		}
		# Return a message describing what occurred
		if ($unassigned) {
			$this->logger->log_action(
				'Unassigned ' . $email . ' from ' . $username .
				'.'
			);
			return sprintf(
				'<p class="success">Unassigned <b>%s</b> from
				<b>%s</b>!</p>',
				Text::html_encode($email),
				Text::html_encode($username)
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not unassign ' . $email . ' from ' . $username .
			'. MySQL error ' . $error . '.'
		);
		return sprintf(
			'<p class="error">Could not unassign <b>%s</b> from
			<b>%s</b>!<br><br>MySQL error %s.</p>',
			Text::html_encode($email),
			Text::html_encode($username),
			Text::html_encode($error)
		);
	}
	
	/**
	 * Automatically assigns reviews based on reviewers' workloads and areas
	 * of expertise. Returns an HTML message describing what occurred.
	 * Called from assignments.php.
	 */
	public function auto_assign_reviews() {
		# Make sure the current user is allowed to assign reviews
		$gars = GARS::get_instance();
		if ($gars->get_role() != 'chair') {
			$this->logger->log_action(
				'Not allowed to auto-assign reviews.'
			);
			return '<p class="error">You are not allowed to edit
				review assignments!</p>';
		}
		# Select the available reviewers and applications
		$query = "SELECT id, countries, research_areas,
			(num_assigned_reviews + 1) / workload AS cost
			FROM users
			LEFT JOIN (
				SELECT user_id AS id,
				GROUP_CONCAT(country_code SEPARATOR ',') AS countries
				FROM user_countries GROUP BY id
			) AS expert_countries USING(id)
			LEFT JOIN (
				SELECT user_id AS id,
				GROUP_CONCAT(CAST(area_id AS CHAR) SEPARATOR ',') AS research_areas
				FROM user_areas GROUP BY id
			) AS expert_areas USING(id)
			WHERE available = TRUE ORDER BY cost ASC";
		$user_result = $this->database->query($query);
		$query = "SELECT id, citizenship AS country, research_area, reviewers,
			numDesiredReviews - IFNULL(num_reviews, 0) AS num_assignments
			FROM applications
			LEFT JOIN (
				SELECT app_id AS id,
				COUNT(user_id) AS num_reviews,
				GROUP_CONCAT(CAST(user_id AS CHAR) SEPARATOR ',') AS reviewers
				FROM reviews GROUP BY app_id
			) AS review_counts USING(id)
			HAVING num_assignments > 0";
		$app_result = $this->database->query($query);
		# Create assignments
		$assignment = Assignment_Problem::create_assignments($user_result,
			$app_result);
		if (is_string($assignment)) {
			return $assignment;
		}
		# Update the database
		$values = array();
		$num_assignments = count($assignment);
		for ($i = 0; $i < $num_assignments; ++$i) {
			list($user_id, $app_id) = $assignment[$i];
			$values[] = sprintf('(%d, %d, NOW())', $user_id, $app_id);
		}
		$values = implode(', ', $values);
		$query = 'INSERT IGNORE INTO reviews (user_id, app_id, date) VALUES ' .
			$values;
		$assigned = $this->database->query($query);
		$num_assigned = array();
		for ($i = 0; $i < $num_assignments; ++$i) {
			$user_id = $assignment[$i][0];
			if (array_key_exists($user_id, $num_assigned)) {
				$num_assigned[$user_id]++;
			}
			else {
				$num_assigned[$user_id] = 1;
			}
		}
		$values = array();
		foreach ($num_assigned as $user_id => $num) {
			$values[] = sprintf(
				'(%d, %d)', $user_id, $num
			);
		}
		$num_users = count($values);
		$values = implode(', ', $values);
		$query = sprintf(
			"INSERT INTO users (id, num_assigned_reviews) VALUES %s
			ON DUPLICATE KEY UPDATE
			num_assigned_reviews = VALUES(num_assigned_reviews)",
			$values
		);
		$incremented = $this->database->query($query);
		if ($incremented) {
			$this->logger->log_action(
				'Incremented num_assigned_reviews for ' .
				$num_users . ' users.'
			);
		}
		else {
			$error = $this->database->error();
			$this->logger->log_action(
				'Could not increment num_assigned_reviews for ' .
				$num_users . ' users. MySQL error ' . $error . '.'
			);
		}
		# Return a message describing what occurred
		if ($assigned) {
			$this->logger->log_action(
				'Assigned ' . $num_assignments .
				' applications automatically.'
			);
			return sprintf(
				'<p class="success">Assigned <b>%d</b> applications
				to the optimal reviewers automatically!</p>',
				$num_assignments
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not assign applications automatically. MySQL error ' .
			$error . '.');
		return sprintf(
			'<p class="error">Could not assign applications
			automatically!<br><br>MySQL error %s.</p>',
			Text::html_encode($email),
			Text::html_encode($username),
			Text::html_encode($error)
		);
	}
	
	/**
	 * Increments numDesiredReviews for undecided applications that meet the
	 * current review quota. Returns an HTML message describing what occurred.
	 * Called from assignments.php.
	 */
	public function increment_desired_reviews() {
		# Make sure the current user is allowed to edit numDesiredReviews
		$gars = GARS::get_instance();
		if ($gars->get_role() != 'chair') {
			$this->logger->log_action(
				'Not allowed to increment the number of desired reviews.'
			);
			return '<p class="error">You are not allowed to edit the
				number of desired reviews!</p>';
		}
		# Update the database
		$query = "UPDATE applications
			SET numDesiredReviews = numDesiredReviews + 1
			WHERE id IN (SELECT id FROM (
				SELECT id FROM applications
				LEFT JOIN (
					SELECT app_id AS id,
					COUNT(rating) AS num_reviews
					FROM reviews GROUP BY app_id
				) AS review_counts USING(id)
				WHERE numDesiredReviews <= num_reviews AND
				(tier = -1 OR (tier >= 1.5 AND tier < 2.0))
			) AS apps_to_increment)";
		$incremented = $this->database->query($query);
		# Return a message describing what occurred
		if ($incremented) {
			$num_incremented = $this->database->affected_rows();
			$this->logger->log_action(
				'Incremented numDesiredReviews for ' .
				$num_incremented . ' applications.'
			);
			if ($num_incremented == 0) {
				return '<p class="success">No applications needed
					their number of desired reviews incremented.</p>';
			}
			if ($num_incremented == 1) {
				return '<p class="success">Incremented the number
					of desired reviews for <b>1 application</b>!
					You can now auto-assign reviewers for it.</p>';
			}
			return sprintf(
				'<p class="success">Incremented the number of
				desired reviews for <b>%d applications</b>!
				You can now auto-assign reviewers for them.</p>',
				(int)$num_incremented
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not increment numDesiredReviews. MySQL error ' .
			$error . '.'
		);
		return sprintf(
			'<p class="error">Could not increment the number of
			desired reviews!<br><br>MySQL error %s.</p>',
			Text::html_encode($error)
		);
	}
	
	/**
	 * Updates the application database with the data from an uploaded file.
	 * Returns an HTML message describing what occurred.
	 * Called from upload.php.
	 */
	public function upload_file($file, $type, $program) {
		$this->logger->log_action('Uploaded file ' . $file['name'] .
			' as ' . $type . ' for ' . $program . ' program.');
		# Make sure a program was chosen
		if ($program != 'M.S.' && $program != 'Ph.D.') {
			return '<p class="error">Select a program (either M.S.
				or Ph.D.)!</p>';
		}
		# Make sure the file was uploaded
		switch ($file['error']) {
		case UPLOAD_ERR_OK:
			break;
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			return '<p class="error">The file is too large!</p>';
		case UPLOAD_ERR_PARTIAL:
			return '<p class="error">The file was not completely uploaded!</p>';
		case UPLOAD_ERR_NO_FILE:
			return '<p class="error">Select a file to upload!</p>';
		case UPLOAD_ERR_NO_TMP_DIR:
			return '<p class="error">The uploaded file could not be stored!</p>';
		default:
			return '<p class="error">The file could not be uploaded!</p>';
		}
		# Dispatch on the type of file
		$filename = $file['tmp_name'];
		switch ($type) {
		case 'AY':
			$map_file = BASE_PATH . 'maps/' . AY_MAP;
			return $this->upload_mapped_file($filename, $map_file, $program);
		case 'OTS':
			$map_file = BASE_PATH . 'maps/' . OTS_MAP;
			return $this->upload_mapped_file($filename, $map_file, $program);
		case 'PDF':
			return $this->upload_zipped_pdfs($filename, $program);
		default:
			return '<p class="error">Select a file type (either AY,
				OTS, or PDF)!</p>';
		}
	}
	
	/**
	 * Updates the application database with the data from an AY or OTS file.
	 * Returns an HTML message describing what occurred.
	 * Called from the upload_file method.
	 */
	private function upload_mapped_file($data_file, $map_file, $program) {
		# Parse the files into lines and columns
		$parser = new Data_Parser($data_file, $map_file, $this);
		$warnings = array();
		$status = $parser->parse($warnings);
		if (is_string($status)) {
			return $status;
		}
		if (!empty($warnings)) {
			$warnings = '<p class="error">' .
				implode("<br>\n", $warnings) . '</p>';
		}
		else {
			$warnings = '';
		}
		$columns = $parser->get_columns();
		$lines = $parser->get_lines();
		# Assemble column names for query
		$columns = array_map('mysql_real_escape_string', $columns);
		$column_names = implode(', ', $columns);
		$column_names .= ', degreeProgram, uploadDate';
		# Assemble field values for query
		function format_value($value) {
			$value = mysql_real_escape_string($value);
			if ($value == '') {
				return 'NULL';
			}
			return "'" . $value . "'";
		}
		$degree_program = format_value($program);
		$values = array();
		$num_lines = count($lines);
		for ($i = 0; $i < $num_lines; ++$i) {
			$line = array_map('format_value', $lines[$i]);
			$line = implode(', ', $line);
			$line .= ', ' . $degree_program . ', NOW()';
			$values[] = $line;
		}
		$values = '(' . implode('), (', $values) . ')';
		# Assemble column updates for query
		function format_update($column) {
			return $column . '=VALUES(' . $column . ')';
		}
		$updates = array_map('format_update', $columns);
		$updates = implode(', ', $updates);
		# Query the database
		$query = sprintf(
			'INSERT INTO applications (%s) VALUES %s
			ON DUPLICATE KEY UPDATE %s',
			$column_names, $values, $updates
		);
		$inserted = $this->database->query($query);
		# Return a message describing what occurred
		if ($inserted) {
			$this->logger->log_action(
				'Added or updated ' . $num_lines .
				' applications from uploaded data file.'
			);
			if ($num_lines == 1) {
				return $warnings . '<p class="success">Added or
					updated 1 application!</p>';
			}
			return $warnings . sprintf(
				'<p class="success">Added or updated <b>%d</b>
				applications!</p>',
				$num_lines
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not add or update applications from uploaded data file. MySQL error ' .
			$error . '.');
		return $warnings . sprintf(
			'<p class="error">Could not add or update the
			applications!<br><br>MySQL error %s.</p>',
			Text::html_encode($error)
		);
	}
	
	/**
	 * Updates the application database with the PDFs in a ZIP file.
	 * Returns an HTML message describing what occurred.
	 * Called from the upload_file method.
	 */
	private function upload_zipped_pdfs($filename, $program) {
		if (!function_exists('zip_open')) {
			return '<p class="error">This server lacks the PHP ZIP
				extension and cannot open .zip files!</p>';
		}
		$zip = zip_open($filename);
		if (!is_resource($zip)) {
			return '<p class="error">Could not open the .zip file!</p>';
		}
		# Get valid PDF names (email addresses)
		$query = 'SELECT email_address FROM applications';
		$result = $this->database->query($query);
		$emails = array();
		$num_rows = mysql_num_rows($result);
		for ($i = 0; $i < $num_rows; ++$i) {
			$emails[] = mysql_result($result, $i, 0);
		}
		# Check each file in the ZIP
		$warnings = array();
		$num_uploaded = 0;
		while (is_resource($entry = zip_read($zip))) {
			$entry_name = zip_entry_name($entry);
			$name_info = pathinfo($entry_name);
			# Make sure the file is a PDF
			$extension = strtolower($name_info['extension']);
			if ($extension != 'pdf') {
				$warnings[] = sprintf(
					'"<b>%s</b>" is not a .pdf file!',
					Text::html_encode($entry_name)
				);
				continue;
			}
			# Make sure the PDF's applicant is in the database
			$email = $name_info['filename'];
			if (!in_array($email, $emails)) {
				$warnings[] = sprintf(
					'<b>%s</b> is not in the database!',
					Text::html_encode($email)
				);
				continue;
			}
			# Save the PDF file
			$pdf = fopen(BASE_PATH . 'pdfs/' . $entry_name, 'w');
			if (!is_resource($pdf)) {
				$warnings[] = sprintf(
					'Could not save "<b>%s</b>"!',
					Text::html_encode($entry_name)
				);
				continue;
			}
			if (!zip_entry_open($zip, $entry, 'r')) {
				$warnings[] = sprintf(
					'Could not extract "<b>%s</b>"!',
					Text::html_encode($entry_name)
				);
			}
			$size = zip_entry_filesize($entry);
			$content = zip_entry_read($entry, $size);
			fwrite($pdf, $content);
			fclose($pdf);
			zip_entry_close($entry);
			++$num_uploaded;
		}
		zip_close($zip);
		# Return a message describing what occurred
		if (!empty($warnings)) {
			$warnings = '<p class="error">' .
				implode("<br>\n", $warnings) . '</p>';
		}
		else {
			$warnings = '';
		}
		if ($num_uploaded == 1) {
			$this->logger->log_action(
				'Uploaded ' . $num_uploaded . ' PDF.'
			);
			return $warnings . '<p class="success">Uploaded 1 PDF!</p>';
		}
		$this->logger->log_action('Uploaded ' . $num_uploaded . ' PDFs.');
		return $warnings . sprintf(
			'<p class="success">Uploaded %d PDFs!</p>',
			$num_uploaded
		);
	}
	
	/**
	 * Returns a map from research area name/abbreviations to IDs.
	 * Called from the Data_Parser class.
	 */
	public function get_research_areas() {
		$query = 'SELECT id, name, abbr FROM research_areas';
		$result = $this->database->query($query);
		$research_areas = array();
		$num_rows = mysql_num_rows($result);
		for ($i = 0; $i < $num_rows; ++$i) {
			$row = mysql_fetch_assoc($result);
			$id = (int)$row['id'];
			$research_areas[$row['name']] = $id;
			$research_areas[$row['abbr']] = $id;
		}
		return $research_areas;
	}
	
	/**
	 * Returns the last and first names of the applicants with the given
	 * email addresses.
	 * Called from the Data_Parser class.
	 */
	public function get_applicant_names($emails) {
		$num_emails = count($emails);
		$clause = array();
		foreach ($emails as $email) {
			$clause[] = sprintf(
				"email_address='%s'",
				mysql_real_escape_string($email)
			);
		}
		$clause = implode(' OR ', $clause);
		$query = sprintf(
			'SELECT email_address, last_name, first_name
			FROM applications WHERE %s LIMIT %d',
			$clause, $num_emails
		);
		$result = $this->database->query($query);
		$names = array();
		$num_rows = mysql_num_rows($result);
		for ($i = 0; $i < $num_rows; ++$i) {
			$row = mysql_fetch_assoc($result);
			$names[$row['email_address']] = array($row['last_name'],
				$row['first_name']);
		}
		return $names;
	}
	
	/**
	 * Sets the password for a given user. Returns an HTML message
	 * describing what occurred.
	 * Called from set-password.php.
	 */
	public function set_password($username, $password, $creds) {
		if ($username == '') {
			return '<p class="error">Enter a username!</p>';
		}
		# Make sure the user exists
		$query = sprintf(
			"SELECT username FROM users WHERE username='%s' LIMIT 1",
			mysql_real_escape_string($username)
		);
		$exists = $this->database->query($query);
		if (mysql_num_rows($exists) < 1) {
			return sprintf(
				'<p class="error">No user exists named <b>%s</b>!</p>',
				Text::html_encode($username)
			);
		}
		# Generate a new salt and calculate the appropriate hash
		$salt = $creds->generate_salt();
		$hash = $creds->hash($password, $salt);
		# Update the database
		$query = sprintf(
			"UPDATE users SET pass_hash = '%s', pass_salt = '%s'
			WHERE username = '%s' LIMIT 1",
			mysql_real_escape_string($hash),
			mysql_real_escape_string($salt),
			mysql_real_escape_string($username)
		);
		$set = $this->database->query($query);
		# Return a message describing what occurred
		if ($set) {
			$this->logger->log_action(
				'Set password for ' . $username . '.'
			);
			return sprintf(
				'<p class="success">Set the password for
				<b>%s</b>!</p>',
				Text::html_encode($username)
			);
		}
		$error = $this->database->error();
		$this->logger->log_action(
			'Could not set password for ' . $username .
			'. MySQL error ' . $error . '.'
		);
		return sprintf(
			'<p class="error">Could not set the password for
			<b>%s</b>!<br><br>MySQL error %s.</p>',
			Text::html_encode($username),
			Text::html_encode($error)
		);
	}
	
	/**
	 * Returns the data from the given table for output to a TSV file.
	 * Called from tsv.php.
	 */
	public function get_table_for_tsv($table) {
		switch ($table) {
		case 'applications':
			$query = "SELECT applications.*,
				/* overriding column names is intentional */
				IF(applications.permanent_resident, 'Y', 'N') as permanent_resident,
				research_areas.name AS research_area,
				countries.name AS citizenship,
				DATE_FORMAT(applications.submission_date, '%m/%d/%Y') AS submission_date,
				DATE_FORMAT(applications.uploadDate, '%h:%i %p, %m/%d/%Y') AS uploadDate
				FROM applications
				LEFT JOIN countries ON applications.citizenship = countries.code
				LEFT JOIN research_areas ON applications.research_area = research_areas.id
				ORDER BY last_name ASC, first_name ASC";
			break;
		case 'reviews':
			$query = "SELECT reviews.*,
				/* overriding column names is intentional */
				users.username AS user_id,
				applications.email_address AS app_id,
				DATE_FORMAT(reviews.date, '%h:%i %p, %m/%d/%Y') AS date
				FROM reviews
				LEFT JOIN users ON reviews.user_id = users.id
				LEFT JOIN applications ON reviews.app_id = applications.id
				ORDER BY date ASC";
			break;
		case 'prior_reviews':
			$query = "SELECT prior_reviews.*,
				/* overriding column names is intentional */
				users.username AS user_id,
				applications.email_address AS app_id,
				DATE_FORMAT(prior_reviews.date, '%h:%i %p, %m/%d/%Y') AS date
				FROM prior_reviews
				LEFT JOIN users ON prior_reviews.user_id = users.id
				LEFT JOIN applications ON prior_reviews.app_id = applications.id
				ORDER BY date ASC";
			break;
		default:
			$query = sprintf(
				'SELECT * FROM %s',
				mysql_real_escape_string($table)
			);
		}
		$result = $this->database->query($query);
		return $result;
	}
}

?>