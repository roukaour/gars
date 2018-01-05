<?php

/**
 * This class accesses the MySQL database. It handles the details of querying
 * the database, but specific queries are constructed and executed by the
 * Bridge class.
 */
class Database {
	# Database information
	private $host;
	private $username;
	private $password;
	private $database;
	# Link to the database
	private $link;
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->host = DB_HOST;
		$this->username = DB_USERNAME;
		$this->password = DB_PASSWORD;
		$this->database = DB_NAME;
	}
	
	/**
	 * Returns whether this Database is connected to the actual MySQL database.
	 */
	public function is_connected() {
		return !empty($this->link);
	}
	
	/**
	 * Connects to the MySQL database and returns whether it was successful.
	 */
	public function connect() {
		if ($this->is_connected()) return;
		$this->link = mysql_connect($this->host, $this->username, $this->password);
		if (!$this->link) return false;
		$created = mysql_query(sprintf(
			'CREATE DATABASE IF NOT EXISTS %s',
			$this->database),
			$this->link
		);
		if (!$created) return false;
		$selected = mysql_select_db($this->database, $this->link);
		if (!$selected) return false;
		return true;
	}
	
	/**
	 * Disconnects from the database and returns whether it was successful.
	 */
	public function disconnect() {
		if (!$this->is_connected()) return true;
		$closed = mysql_close($this->link);
		if (!$closed) return false;
		$this->link = null;
		return true;
	}
	
	/**
	 * Queries the database and returns a response, or false if not connected.
	 */
	public function query($query) {
		if (!$this->is_connected()) return false;
		return mysql_query($query, $this->link);
	}
	
	/**
	 * Returns the number of rows affected by the last query.
	 */
	public function affected_rows() {
		return mysql_affected_rows($this->link);
	}
	
	/**
	 * Returns the error message from the last query.
	 */
	public function error() {
		return mysql_errno($this->link) . ': ' . mysql_error($this->link);
	}
	
	/**
	 * Creates tables in database and returns whether it was successful.
	 */
	public function create_tables() {
		$created = true;
		# Create users table
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS users (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			username VARCHAR(80) NOT NULL UNIQUE,
			pass_hash BINARY(64) NOT NULL,
			pass_salt BINARY(64) NOT NULL,
			role ENUM('chair', 'faculty', 'staff') NOT NULL,
			email VARCHAR(80) NOT NULL UNIQUE,
			num_assigned_reviews INT UNSIGNED DEFAULT 0,
			available BOOLEAN DEFAULT TRUE,
			workload DOUBLE DEFAULT 1.0,
			PRIMARY KEY (id)
		)", $this->link);
		# Create countries table
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS countries (
			code CHAR(2) NOT NULL,
			name VARCHAR(60) NOT NULL,
			PRIMARY KEY (code)
		)", $this->link);
		# Create research_areas table
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS research_areas (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(80) NOT NULL UNIQUE,
			abbr VARCHAR(40) NOT NULL,
			PRIMARY KEY (id)
		)", $this->link);
		# Create user_countries table, associating users with countries
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS user_countries (
			user_id INT UNSIGNED NOT NULL,
			country_code CHAR(2) NOT NULL,
			CONSTRAINT FK_user_id FOREIGN KEY (user_id) REFERENCES users(id),
			CONSTRAINT FK_country_code FOREIGN KEY (country_code) REFERENCES countries(code)
		)", $this->link);
		# Create user_areas table, associating users with research_areas
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS user_areas (
			user_id INT UNSIGNED NOT NULL,
			area_id INT UNSIGNED NOT NULL,
			CONSTRAINT FK_user_id FOREIGN KEY (user_id) REFERENCES users(id),
			CONSTRAINT FK_area_id FOREIGN KEY (area_id) REFERENCES research_areas(id)
		)", $this->link);
		# Create applications table
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS applications (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			/* from AY file */
			submission_date DATE,
			client_ID VARCHAR(16),
			last_name VARCHAR(60),
			first_name VARCHAR(60),
			middle_name VARCHAR(60),
			birth_month INT UNSIGNED,
			birth_day INT UNSIGNED,
			birth_year INT UNSIGNED,
			gender VARCHAR(2),
			ethnicity VARCHAR(40),
			race VARCHAR(40),
			citizenship VARCHAR(2), /* standardized from country-map.txt */
			permanent_resident BOOLEAN,
			phone VARCHAR(16),
			email_address VARCHAR(80) NOT NULL UNIQUE,
			specialization VARCHAR(128),
			GRE_V INT UNSIGNED,
			GRE_V_pctile INT UNSIGNED,
			GRE_Q INT UNSIGNED,
			GRE_Q_pctile INT UNSIGNED,
			GRE_A DOUBLE,
			GRE_A_pctile INT UNSIGNED,
			GRE_subj_name VARCHAR(40),
			GRE_subj_score INT UNSIGNED,
			TOEFL INT UNSIGNED,
			IELTS DOUBLE,
			TOEFL_internet INT UNSIGNED,
			research_area INT UNSIGNED, /* associated with research_areas */
			research_topics VARCHAR(80),
			ug_rank INT UNSIGNED,
			ug_out_of INT UNSIGNED,
			grad_rank INT UNSIGNED,
			grad_out_of INT UNSIGNED,
			theory_course_title VARCHAR(60),
			theory_scale VARCHAR(20),
			theory_grade VARCHAR(20),
			theory_SBU_equiv VARCHAR(60),
			algorithm_course_title VARCHAR(60),
			algorithm_scale VARCHAR(20),
			algorithm_grade VARCHAR(20),
			algorithm_SBU_equiv VARCHAR(60),
			prog_lang_course_title VARCHAR(60),
			prog_lang_scale VARCHAR(20),
			prog_lang_grade VARCHAR(20),
			prog_lang_SBU_equiv VARCHAR(60),
			os_course_title VARCHAR(60),
			os_scale VARCHAR(20),
			os_grade VARCHAR(20),
			os_SBU_equiv VARCHAR(60),
			ug_inst VARCHAR(60), /* standardized from institution-map.txt */
			ug_GPA DOUBLE,
			ug_scale VARCHAR(20),
			ug_GPA1 DOUBLE,
			ug_GPA2 DOUBLE,
			ug_GPA3 DOUBLE,
			ug_GPA4 DOUBLE,
			ug_GPA5 DOUBLE,
			grad_inst VARCHAR(60), /* standardized from institution-map.txt */
			grad_GPA DOUBLE,
			grad_scale VARCHAR(20),
			grad_GPA1 DOUBLE,
			grad_GPA2 DOUBLE,
			otherInfo TEXT,
			/* from OTS file */
			ofcl_GRE_V INT UNSIGNED,
			ofcl_GRE_V_pctile INT UNSIGNED,
			ofcl_GRE_Q INT UNSIGNED,
			ofcl_GRE_Q_pctile INT UNSIGNED,
			ofcl_GRE_A DOUBLE,
			ofcl_GRE_A_pctile INT UNSIGNED,
			ofcl_GRE_subj INT UNSIGNED,
			ofcl_GRE_subj_pctile INT UNSIGNED,
			ofcl_GRE_subj_name VARCHAR(40),
			ofcl_TOEFL_total INT UNSIGNED,
			ofcl_TOEFL_listen INT UNSIGNED,
			ofcl_TOEFL_read INT UNSIGNED,
			ofcl_TOEFL_speak INT UNSIGNED,
			ofcl_TOEFL_write INT UNSIGNED,
			/* Entered manually */
			degreeProgram ENUM('M.S.', 'Ph.D.') NOT NULL,
			tier DOUBLE NOT NULL DEFAULT -1.0,
			summary TEXT,
			TOEFLcomments TEXT,
			numDesiredReviews INT UNSIGNED NOT NULL DEFAULT 1,
			SBU_ID VARCHAR(9),
			SBU_GPA DOUBLE,
			/* computed by GARS */
			uploadDate DATETIME NOT NULL,
			avgRating DOUBLE,
			PRIMARY KEY (id),
			CONSTRAINT FK_research_area FOREIGN KEY (research_area) REFERENCES research_areas(id)
		)", $this->link);
		# Create reviews table, associating users with applications
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS reviews (
			user_id INT UNSIGNED NOT NULL,
			app_id INT UNSIGNED NOT NULL,
			rating DOUBLE,
			review TEXT,
			date DATETIME NOT NULL,
			CONSTRAINT FK_user_id FOREIGN KEY (user_id) REFERENCES users(id),
			CONSTRAINT FK_app_id FOREIGN KEY (app_id) REFERENCES applications(id)
		)", $this->link);
		# Create prior_reviews table
		$created &= mysql_query("CREATE TABLE IF NOT EXISTS prior_reviews
			LIKE reviews", $this->link);
		return $created;
	}
}

?>