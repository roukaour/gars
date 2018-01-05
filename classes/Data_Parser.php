<?php

use_class('Bridge');
use_class('Page');
use_class('Text');

/**
 * This class parses AY or OTS data and mapping files.
 */
class Data_Parser {
	# The name of the data file
	private $data_file;
	# The name of the mapping file
	private $map_file;
	# The bridge to a database
	private $bridge;
	# The status message of this parsing attempt
	private $status;
	# The column names used by the data file
	private $data_columns;
	# The corresponding database column names
	private $db_columns;
	# The column indexes that go in the otherInfo column
	private $other_fields;
	# The lines of tab-separated data
	private $lines;
	# The handle to the incompletely parsed data file
	private $handle;
	# The cached parsed maps
	private $maps;
	
	/**
	 * Constructor.
	 */
	public function __construct($data_file, $map_file, $bridge) {
		$this->data_file = $data_file;
		$this->map_file = $map_file;
		$this->bridge = $bridge;
		$this->status = null;
		$this->data_columns = null;
		$this->db_columns = null;
		$this->other_fields = null;
		$this->lines = null;
		$this->handle = null;
		$this->maps = array();
	}
	
	/**
	 * Returns the array of parsed database columns.
	 */
	public function get_columns() {
		return $this->db_columns;
	}
	
	/**
	 * Returns the array of parsed tab-separated values.
	 */
	public function get_lines() {
		return $this->lines;
	}
	
	/**
	 * Parses the data file and mapping file. Sets $status.
	 * Returns the status message, or null if successful.
	 * Called from the upload_file_ay and upload_file_ots methods.
	 */
	public function parse(&$warnings) {
		# Don't repeat effort if $status is already set
		if (!is_null($this->status)) {
			return $this->status;
		}
		# Parse and check the mapping file
		$parsed = $this->parse_mapping_file();
		if (is_string($parsed)) {
			$this->status = $parsed;
			return $this->status;
		}
		# Begin parsing the data file and check its headers
		$checked = $this->check_headers();
		if (is_string($checked)) {
			$this->status = $checked;
			return $this->status;
		}
		# Parse the data file into lines
		$parsed = $this->parse_data_file();
		if (is_string($parsed)) {
			$this->status = $parsed;
			return $this->status;
		}
		# Make sure names are correct
		$checked = $this->check_names();
		if (is_string($checked)) {
			$this->status = $checked;
			return $this->status;
		}
		# Convert certain values in lines
		$parsed = $this->parse_lines();
		if (is_string($parsed)) {
			$this->status = $parsed;
			return $this->status;
		}
		# Validate certain values in lines
		$validated = $this->validate_lines();
		if (is_string($validated)) {
			$this->status = $validated;
			return $this->status;
		}
		$warnings = $validated;
		# Remove unused columns and values
		$num_columns = count($this->db_columns);
		$num_lines = count($this->lines);
		for ($i = 0; $i < $num_columns; ++$i) {
			if (is_string($this->db_columns[$i])) {
				continue;
			}
			unset($this->db_columns[$i]);
			for ($j = 0; $j < $num_lines; ++$j) {
				unset($this->lines[$j][$i]);
			}
		}
		return $this->status;
	}
	
	/**
	 * Parses the mapping file. Sets $data_columns, $db_columns, and $other_fields.
	 * Returns an HTML status message if an error occurred, otherwise null.
	 */
	private function parse_mapping_file() {
		# Don't repeat effort if $db_columns is already set
		if (!is_null($this->db_columns)) {
			return $this->status;
		}
		# Initialize member variables
		$this->data_columns = array();
		$this->db_columns = array();
		$this->other_fields = array();
		# Read mapping file
		$handle = fopen($this->map_file, 'r');
		if ($handle === false) {
			return '<p class="error">Could not read the mapping file!</p>';
		}
		$seen_email = false;
		# Parse mapping file, line by line
		for ($ln = 0; ($line = fgets($handle)) !== false; ++$ln) {
			$line = trim($line);
			# Empty line indicates end of file
			if (empty($line)) {
				break;
			}
			# Parse and store column names
			list($data_col, $db_col) = split('->', $line, 2);
			if ($db_col == 'ignore') {
				$db_col = null;
			}
			elseif ($db_col == 'otherInfo') {
				$this->other_fields[] = $ln;
				$db_col = null;
			}
			else {
				if ($db_col == 'email_address') {
					$seen_email = true;
				}
				elseif ($db_col == 'ID') {
					$db_col = 'client_ID';
				}
				if (in_array($db_col, $this->db_columns)) {
					fclose($handle);
					return sprintf(
						'<p class="error">The mapping file
						has a duplicate column "%s"
						defined on line %d!</p>',
						$db_col, $ln+1
					);
				}
			}
			$this->data_columns[] = $data_col;
			$this->db_columns[] = $db_col;
		}
		# Make sure that email_address exists as a database column
		if (!$seen_email) {
			return '<p class="error">The mapping file does not map
				any header to the "email_address" column!</p>';
		}
		fclose($handle);
		return null;
	}
	
	/**
	 * Begins parsing the data file and makes sure its headers are correct.
	 * Depends on $data_columns having been set by parse_map. Sets $handle.
	 * Returns an HTML status message if an error occurred, otherwise null.
	 */
	private function check_headers() {
		# Make sure $data_columns is set
		if (is_null($this->data_columns)) {
			return '<p class="error">No data columns!</p>';
		}
		# Don't repeat effort if $handle is already set
		if (!is_null($this->handle)) {
			return $this->status;
		}
		$num_columns = count($this->data_columns);
		# Open data file
		$handle = fopen($this->data_file, 'r');
		if ($handle === false) {
			return '<p class="error">Could not read the data file!</p>';
		}
		# Parse headers
		$headers = fgetcsv($handle, 0, "\t", '"');
		if ($headers === false || $headers == array(null)) {
			fclose($handle);
			return '<p class="error">The data file is empty or begins
				with a blank line!</p>';
		}
		$num_headers = count($headers);
		# Make sure the data file's headers match those from the mapping file
		if ($num_headers != $num_columns) {
			fclose($handle);
			return sprintf(
				'<p class="error">The mapping file defines %d
				columns, but the data file has %d headers!</p>',
				$num_columns, $num_headers
			);
		}
		for ($i = 0; $i < $num_headers; ++$i) {
			$header = $headers[$i];
			$column = $this->data_columns[$i];
			if ($header != $column) {
				fclose($handle);
				return sprintf(
					'<p class="error">The header "%s" does
					not match "%s" defined in the mapping
					file on line %d!</p>',
					Text::html_encode($header),
					Text::html_encode($column),
					$ln+1
				);
			}
		}
		# Set $handle
		$this->handle = $handle;
		return null;
	}
	
	/**
	 * Parses the data file. Depends on $handle having been set by check_headers.
	 * Sets $lines. Returns an HTML message if an error occurred, otherwise null.
	 */
	private function parse_data_file() {
		# Make sure $handle is set
		if (is_null($this->handle)) {
			return '<p class="error">No handle!</p>';
		}
		# Don't repeat effort if $lines is already set
		if (!is_null($this->lines)) {
			return $this->status;
		}
		# Initialize $lines
		$this->lines = array();
		$num_columns = count($this->db_columns);
		$emails = array();
		$email_column = array_search('email_address', $this->db_columns);
		# Parse data file, line by line
		for ($ln = 1; ($line = fgetcsv($this->handle, 0, "\t", '"'))
			!== false; ++$ln) {
			# Blank line indicates end of file
			if ($line == array(null)) {
				break;
			}
			# Make sure fields match headers
			$num_fields = count($line);
			if ($num_fields != $num_columns) {
				fclose($this->handle);
				return sprintf(
					'<p class="error">Line %d has %d fields
					when it should have %d!</p>',
					$ln+1, $num_fields, $num_columns
				);
			}
			# Make sure the email address is unique
			$email = $line[$email_column];
			if (empty($email)) {
				fclose($this->handle);
				return sprintf(
					'<p class="error">The data file is missing
					an email address on line %d!</p>',
					$ln+1
				);
			}
			elseif (in_array($email, $emails)) {
				fclose($this->handle);
				return sprintf(
					'<p class="error">The data file has a
					duplicate email address "%s" on line %d!</p>',
					Text::html_encode($email), $ln+1
				);
			}
			$emails[] = $email;
			# Store the data line for future processing
			$this->lines[] = $line;
		}
		fclose($this->handle);
		return null;
	}
	
	/**
	 * Makes sure the names on each line match those in the database.
	 * Depends on $lines having been set by parse_data. Returns an HTML
	 * message if an error occurred, otherwise null.
	 */
	private function check_names() {
		# Make sure $lines is set
		if (is_null($this->lines)) {
			return '<p class="error">No lines!</p>';
		}
		$num_lines = count($this->lines);
		# Get column indexes
		$email_column = array_search('email_address', $this->db_columns);
		$last_name_column = array_search('last_name', $this->db_columns);
		$first_name_column = array_search('first_name', $this->db_columns);
		# Collect the email addresses
		$emails = array();
		for ($i = 0; $i < $num_lines; ++$i) {
			$emails[] = $this->lines[$i][$email_column];
		}
		# Get the names corresponding to the email addresses
		$names = $this->bridge->get_applicant_names($emails);
		# Make sure the names agree with the ones in the data file
		for ($i = 0; $i < $num_lines; ++$i) {
			$email = $emails[$i];
			if (!array_key_exists($email, $names)) {
				continue;
			}
			list($real_last_name, $real_first_name) = $names[$email];
			$last_name = $this->lines[$i][$last_name_column];
			$first_name = $this->lines[$i][$first_name_column];
			if ($last_name != $real_last_name || $first_name != $real_first_name) {
				return sprintf(
					'<p class="error">The name of %s in the
					database, "%s, %s", does not match the
					name on line %d, "%s, %s"!</p>',
					Text::html_encode($email),
					Text::html_encode($real_last_name),
					Text::html_encode($real_first_name),
					$i+2,
					Text::html_encode($last_name),
					Text::html_encode($first_name)
				);
			}
		}
		return null;
	}
	
	/**
	 * Converts certain strings in each line based on their column.
	 * Depends on $lines having been set by parse_data.
	 * Returns an HTML message if an error occurred, otherwise null.
	 */
	private function parse_lines() {
		# Make sure $lines is set
		if (is_null($this->lines)) {
			return '<p class="error">No lines!</p>';
		}
		# Construct otherInfo column
		$num_lines = count($this->lines);
		$num_other = count($this->other_fields);
		$this->db_columns[] = 'otherInfo';
		for ($i = 0; $i < $num_lines; ++$i) {
			$other_info = array();
			for ($j = 0; $j < $num_other; ++$j) {
				$index = $this->other_fields[$j];
				$column = $this->data_columns[$index];
				$value = $this->lines[$i][$index];
				$other_info[] = "$column=$value";
			}
			$other_info = implode('; ', $other_info);
			$this->lines[$i][] = $other_info;
		}
		# Modify column values
		$num_columns = count($this->db_columns);
		for ($i = 0; $i < $num_columns; ++$i) {
			$column = $this->db_columns[$i];
			switch ($column) {
			# Convert to integer
			case 'birth_month':
			case 'birth_day':
			case 'birth_year':
			case 'GRE_V':
			case 'GRE_V_pctile':
			case 'GRE_Q':
			case 'GRE_Q_pctile':
			case 'GRE_A_pctile':
			case 'GRE_subj_score':
			case 'TOEFL':
			case 'TOEFL_internet':
			case 'ug_rank':
			case 'ug_out_of':
			case 'grad_rank':
			case 'grad_out_of':
			case 'ofcl_GRE_V':
			case 'ofcl_GRE_V_pctile':
			case 'ofcl_GRE_Q':
			case 'ofcl_GRE_Q_pctile':
			case 'ofcl_GRE_A_pctile':
			case 'ofcl_GRE_subj':
			case 'ofcl_GRE_subj_pctile':
			case 'ofcl_TOEFL_total':
			case 'ofcl_TOEFL_listen':
			case 'ofcl_TOEFL_read':
			case 'ofcl_TOEFL_speak':
			case 'ofcl_TOEFL_write':
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if ($value == '') {
						$this->lines[$j][$i] = null;
						continue;
					}
					$this->lines[$j][$i] = (int)$value;
				}
				break;
			# Convert to double
			case 'GRE_A':
			case 'IELTS':
			case 'ug_GPA':
			case 'ug_GPA1':
			case 'ug_GPA2':
			case 'ug_GPA3':
			case 'ug_GPA4':
			case 'ug_GPA5':
			case 'grad_GPA':
			case 'grad_GPA1':
			case 'grad_GPA2':
			case 'ofcl_GRE_A':
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if ($value == '') {
						$this->lines[$j][$i] = null;
						continue;
					}
					$this->lines[$j][$i] = (double)$value;
				}
				break;
			# Convert to Boolean
			case 'permanent_resident':
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = strtolower(substr(
						$this->lines[$j][$i], 0, 1));
					$this->lines[$j][$i] = $value != 'n';
				}
				break;
			# Convert MM/DD/YYYY to YYYY-MM-DD
			case 'submission_date':
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if (empty($value)) {
						$this->lines[$j][$i] = null;
						continue;
					}
					$this->lines[$j][$i] = preg_replace(
						'/(\d+)\/(\d+)\/(\d+)/', 
						'${3}-${1}-${2}', $value);
				}
				break;
			# Remove non-numeric characters
			case 'phone':
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if (empty($value)) {
						$this->lines[$j][$i] = null;
						continue;
					}
					$this->lines[$j][$i] = preg_replace(
						'/\D/', '', $value);
				}
				break;
			# Standardize from country map
			case 'citizenship':
				$map = $this->parse_map(BASE_PATH . 'maps/' . COUNTRY_MAP);
				if (is_string($map)) {
					return $map;
				}
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if (empty($value)) {
						$this->lines[$j][$i] = null;
						continue;
					}
					if (!array_key_exists($value, $map)) {
						return sprintf(
							'<p class="error">The country
							map does not map "%s" to
							any country code!</p>',
							Text::html_encode($value)
						);
					}
					$this->lines[$j][$i] = $map[$value];
				}
				break;
			# Standardize from institution map
			case 'ug_inst':
			case 'grad_inst':
				$map = $this->parse_map(BASE_PATH . 'maps/' . INSTITUTION_MAP);
				if (is_string($map)) {
					return $map;
				}
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if (empty($value)) {
						$this->lines[$j][$i] = null;
						continue;
					}
					if (array_key_exists($value, $map)) {
						$this->lines[$j][$i] = $map[$value];
					}
				}
				break;
			# Convert to associated research area
			case 'research_area':
				$research_areas = $this->bridge->get_research_areas();
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if (empty($value)) {
						$this->lines[$j][$i] = null;
						continue;
					}
					if (!array_key_exists($value, $research_areas)) {
						return sprintf(
							'<p class="error">There is
							no research area named or
							abbreviated as "%s"!</p>',
							Text::html_encode($value)
						);
					}
					$this->lines[$j][$i] = $research_areas[$value];
				}
				break;
			# Escape strings
			default:
				for ($j = 0; $j < $num_lines; ++$j) {
					$value = $this->lines[$j][$i];
					if (empty($value)) {
						$this->lines[$j][$i] = null;
						continue;
					}
					$this->lines[$j][$i] = mysql_real_escape_string($value);
				}
			}
		}
		return null;
	}
	
	/**
	 * Validates certain values in each line based on their column.
	 * Depends on $lines having been set by parse_data.
	 * Returns an HTML message if an error occurred, otherwise the warnings.
	 */
	private function validate_lines() {
		# Make sure $lines is set
		if (is_null($this->lines)) {
			return '<p class="error">No lines!</p>';
		}
		$num_lines = count($this->lines);
		$warnings = array();
		# Get columns to validate
		$email_col = array_search('email_address', $this->db_columns);
		$gre_v_col = array_search('GRE_V', $this->db_columns);
		$gre_q_col = array_search('GRE_Q', $this->db_columns);
		$gre_a_col = array_search('GRE_A', $this->db_columns);
		$ofcl_gre_v_col = array_search('ofcl_GRE_V', $this->db_columns);
		$ofcl_gre_q_col = array_search('ofcl_GRE_Q', $this->db_columns);
		$ofcl_gre_a_col = array_search('ofcl_GRE_A', $this->db_columns);
		$toefl_l_col = array_search('ofcl_TOEFL_listen', $this->db_columns);
		$toefl_r_col = array_search('ofcl_TOEFL_read', $this->db_columns);
		$toefl_s_col = array_search('ofcl_TOEFL_speak', $this->db_columns);
		$toefl_w_col = array_search('ofcl_TOEFL_write', $this->db_columns);
		$toefl_t_col = array_search('ofcl_TOEFL_total', $this->db_columns);
		# Go through the lines
		for ($i = 0; $i < $num_lines; ++$i) {
			$email_address = $this->lines[$i][$email_col];
			# Make sure GRE_V is between 0 and 800
			if ($gre_v_col !== false) {
				$gre_v = $this->lines[$i][$gre_v_col];
				if (!is_null($gre_v) && ((int)$gre_v < 0 ||
					(int)$gre_v > 800)) {
					$warnings[] = sprintf(
						'%s has an invalid GRE Verbal
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$gre_v, $i+2
					);
				}
			}
			# Make sure GRE_Q is between 0 and 800
			if ($gre_q_col !== false) {
				$gre_q = $this->lines[$i][$gre_q_col];
				if (!is_null($gre_q) && ((int)$gre_q < 0 ||
					(int)$gre_q > 800)) {
					$warnings[] = sprintf(
						'%s has an invalid GRE Quantitative
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$gre_q, $i+2
					);
				}
			}
			# Make sure GRE_A is between 0 and 800
			if ($gre_a_col !== false) {
				$gre_a = $this->lines[$i][$gre_a_col];
				if (!is_null($gre_a) && ((int)$gre_a < 0 ||
					(int)$gre_a > 800)) {
					$warnings[] = sprintf(
						'%s has an invalid GRE Analytical
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$gre_a, $i+2
					);
				}
			}
			# Make sure ofcl_GRE_V is between 0 and 800
			if ($ofcl_gre_v_col !== false) {
				$ofcl_gre_v = $this->lines[$i][$ofcl_gre_v_col];
				if (!is_null($ofcl_gre_v) && ((int)$ofcl_gre_v < 0 ||
					(int)$ofcl_gre_v > 800)) {
					$warnings[] = sprintf(
						'%s has an invalid GRE Verbal
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$ofcl_gre_v, $i+2
					);
				}
			}
			# Make sure ofcl_GRE_Q is between 0 and 800
			if ($ofcl_gre_q_col !== false) {
				$ofcl_gre_q = $this->lines[$i][$ofcl_gre_q_col];
				if (!is_null($ofcl_gre_q) && ((int)$ofcl_gre_q < 0 ||
					(int)$ofcl_gre_q > 800)) {
					$warnings[] = sprintf(
						'%s has an invalid GRE Quantitative
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$ofcl_gre_q, $i+2
					);
				}
			}
			# Make sure ofcl_GRE_V is between 0 and 800
			if ($ofcl_gre_a_col !== false) {
				$ofcl_gre_a = $this->lines[$i][$ofcl_gre_a_col];
				if (!is_null($ofcl_gre_a) && ((int)$ofcl_gre_a < 0 ||
					(int)$ofcl_gre_a > 800)) {
					$warnings[] = sprintf(
						'%s has an invalid GRE Analytical
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$ofcl_gre_a, $i+2
					);
				}
			}
			# Make sure ofcl_TOEFL_total is correct
			if ($toefl_l_col !== false && $toefl_r_col !== false &&
				$toefl_s_col !== false && $toefl_w_col !== false &&
				$toefl_t_col !== false) {
				$toefl_l = $this->lines[$i][$toefl_l_col];
				$toefl_r = $this->lines[$i][$toefl_r_col];
				$toefl_s = $this->lines[$i][$toefl_s_col];
				$toefl_w = $this->lines[$i][$toefl_w_col];
				$toefl_t = $this->lines[$i][$toefl_t_col];
				$actual_t = (int)$toefl_l + (int)$toefl_r +
					(int)$toefl_s + (int)$toefl_w;
				if (!is_null($toefl_l) && !is_null($toefl_r) &&
					!is_null($toefl_s) && !is_null($toefl_w) &&
					!is_null($toefl_t) && (int)$toefl_t !=
					$actual_t) {
					$warnings[] = sprintf(
						'%s has an incorrect total TOEFL
						score (%d) on line %d!',
						Text::html_encode($email_address),
						(int)$toefl_t, $i+2
					);
				}
			}
		}
		return $warnings;
	}
	
	/**
	 * Parses a map file (lines with key->value) and caches the result.
	 * Returns an HTML message if an error occurred, otherwise the map.
	 * Called from the parse_lines method.
	 */
	private function parse_map($map_file) {
		# Don't repeat effort if $map_file is already parsed
		if (array_key_exists($map_file, $this->maps)) {
			return $this->maps[$map_file];
		}
		$map = array();
		# Read map file
		$handle = fopen($map_file, 'r');
		if ($handle === false) {
			return sprintf(
				'<p class="error">Could not read "%s"!</p>',
				Text::html_encode($map_file)
			);
		}
		# Parse map file, line by line
		for ($ln = 0; ($line = fgets($handle)) !== false; ++$ln) {
			$line = trim($line);
			# Empty line indicates end of file
			if (empty($line)) {
				break;
			}
			# Parse and store column names
			list($key, $value) = split('->', $line, 2);
			$map[$key] = $value;
		}
		fclose($handle);
		# Cache the parsed map file
		$this->maps[$map_file] = $map;
		return $map;
	}
}