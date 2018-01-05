<?php

/**
 * This class uses the Hungarian method (also called the Kuhn-Munkres algorithm)
 * to solve the assignment problem in O(n^3) time. It has been adapted from a
 * description of the algorithm on Wikipedia. GARS uses it to automatically
 * assign applications to reviewers, given that each assignment has a cost
 * based on the reviewer's workload and expertise.
 */
class Assignment_Problem {
	# Values for the $mask array
	const MASK_NONE = 0;
	const MASK_STAR = 1;
	const MASK_PRIME = 2;
	
	# The matrix of costs to minimize
	private $cost;
	# The number of rows in the cost matrix
	private $num_rows;
	# The number of columns in the cost matrix
	private $num_cols;
	# The number of entries in the cost matrix
	private $num_entries;
	# Mask values for each cell in the cost matrix
	private $mask;
	# Cover values for each row in the cost matrix
	private $row_cover;
	# Cover values for each column in the cost matrix
	private $col_cover;
	# The maximum cost in the cost matrix
	private $max_cost;
	# The location at which to start following a path
	private $path_rc;
	
	/**
	 * Returns pairs of reviews and applications from the given result sets,
	 * or an HTML message if an error occurred.
	 * Called from Bridge.php.
	 */
	public static function create_assignments($user_result, $app_result) {
		$num_users = mysql_num_rows($user_result);
		if ($num_users == 0) {
			return '<p class="error">There are no users available to
				review applications!</p>';
		}
		$num_apps = mysql_num_rows($app_result);
		if ($num_apps == 0) {
			return '<p class="success">There are no applications
				that need review assignments.</p>';
		}
		$problem_size = max($num_users, $num_apps);
		# Make arrays of users, applications, and costs
		$users = array();
		$apps = array();
		$costs = array_fill(0, $problem_size, array_fill(0,
			$problem_size, 0));
		for ($i = 0; $i < $problem_size; ++$i) {
			# Get user data from database result
			if ($i < $num_users) {
				$user = mysql_fetch_assoc($user_result);
				$users[] = array(
					'id' => $user['id'],
					'cost' => (double)$user['cost'],
					'countries' => explode(',', $user['countries']),
					'research_areas' => explode(',', $user['research_areas'])
				);
			}
			$user = $users[$i % $num_users];
			for ($j = 0; $j < $problem_size; ++$j) {
				# Get app data from database result
				if ($j >= $num_apps) {
					$costs[$i][$j] = 0;
					continue;
				}
				if ($i == 0) {
					$app = mysql_fetch_assoc($app_result);
					$apps[] = array(
						'id' => $app['id'],
						'num_assignments' => (int)$app['num_assignments'],
						'country' => $app['country'],
						'research_area' => $app['research_area'],
						'reviewers' => explode(',', $app['reviewers'])
					);
				}
				$app = $apps[$j];
				# Calculate cost of this user reviewing this app
				$cost = $user['cost'];
				if (in_array($app['country'], $user['countries'])) {
					$cost /= COUNTRY_IMPORTANCE;
				}
				if (in_array($app['research_area'], $user['research_areas'])) {
					$cost /= RESEARCH_AREA_IMPORTANCE;
				}
				if (in_array($user['id'], $app['reviewers'])) {
					$cost = 0xDEAD; # Too big to assign
				}
				$costs[$i][$j] = $cost;
			}
		}
		# Solve the assignment problem for these costs
		$problem = new self($costs);
		$pairs = $problem->find_assignment();
		$assignment = array();
		$num_assignments = count($pairs);
		# Get user/app IDs corresponding to indices
		foreach ($pairs as $pair) {
			list($i, $j) = $pair;
			if ($j >= $num_apps) {
				continue;
			}
			$i %= $num_users;
			$assignment[] = array(
				$users[$i]['id'],
				$apps[$j]['id']
			);
		}
		return $assignment;
	}
	
	/**
	 * Constructor. Assumes that $matrix is square.
	 */
	public function __construct($matrix) {
		$this->cost = $matrix;
		$this->num_rows = count($this->cost);
		$this->num_cols = count($this->cost[0]);
		$this->num_entries = $this->num_rows * $this->num_cols;
		$this->mask = array_fill(0, $this->num_rows,
			array_fill(0, $this->num_cols, self::MASK_NONE));
		$this->uncover_all();
		$this->max_cost = 0;
		for ($i = 0; $i < $this->num_rows; ++$i) {
			$row_max_cost = max($this->cost[$i]);
			if ($row_max_cost > $this->max_cost) {
				$this->max_cost =  $row_max_cost;
			}
		}
		$this->path_rc = null;
	}
	
	/**
	 * Returns an array of (row, column) index pairs that minimize their
	 * total cost. Runs in O(n^3) time.
	 */
	public function find_assignment() {
		# Find a minimal assignment
		$method = 'minimize_rows';
		while (is_string($method)) {
			$method = $this->$method();
		}
		# Get the starred entries as the minimal-cost assignments
		$assignment = array();
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($this->mask[$i][$j] == self::MASK_STAR) {
					$assignment[] = array($i, $j);
				}
			}
		}
		return $assignment;
	}
	
	/**
	 * Subtract each row's smallest cost from the entire row, ensuring at
	 * least one zero per row. Returns the next method to call.
	 */
	private function minimize_rows() {
		for ($i = 0; $i < $this->num_rows; ++$i) {
			$row_min_cost = min($this->cost[$i]);
			for ($j = 0; $j < $this->num_cols; ++$j) {
				$this->cost[$i][$j] -= $row_min_cost;
			}
		}
		return 'star_uncovered_zeros';
	}
	
	/**
	 * Stars uncovered zeros and covers their rows and columns. Returns
	 * the next method to call.
	 */
	private function star_uncovered_zeros() {
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($this->cost[$i][$j] <= 0 &&
					!$this->row_cover[$i] &&
					!$this->col_cover[$j]) {
					$this->mask[$i][$j] = self::MASK_STAR;
					$this->row_cover[$i] = true;
					$this->col_cover[$j] = true;
				}
			}
		}
		$this->uncover_all();
		return 'cover_star_cols';
	}
	
	/**
	 * Covers columns of starred zeros. If all columns are so covered,
	 * a minimal assignment has been found. Returns the next method to call.
	 */
	private function cover_star_cols() {
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($this->mask[$i][$j] == self::MASK_STAR) {
					$this->col_cover[$j] = true;
				}
			}
		}
		for ($j = 0; $j < $this->num_cols; ++$j) {
			if (!$this->col_cover[$j]) {
				return 'prime_zeros';
			}
		}
		return null;
	}
	
	/**
	 * Primes uncovered zeros until finding one without a starred cost in
	 * its row or until running out of zeros. Saves the last zero's
	 * location to start a path and returns the next method to call.
	 */
	private function prime_zeros() {
		while (true) {
			$rc = $this->find_uncovered_zero();
			if (is_null($rc)) {
				return 'balance_costs';
			}
			list($row, $col) = $rc;
			$this->mask[$row][$col] = self::MASK_PRIME;
			$col = $this->find_mark_in_row($row, self::MASK_STAR);
			if ($col != -1) {
				$this->row_cover[$row] = true;
				$this->col_cover[$col] = false;
			}
			else {
				$this->path_rc = $rc;
				return 'mark_path';
			}
		}
	}
	
	/**
	 * Modifies the starred entries by following a path of alternating
	 * primed and starred entries. Returns the next method to call.
	 */
	private function mark_path() {
		# Follow a path of alternating primes and stars
		$path = array_fill(0, $this->num_entries, array(-1, -1));
		$count = 0;
		$path[$count] = $this->path_rc;
		$done = false;
		while (!$done) {
			$row = $this->find_mark_in_col($path[$count][1], self::MASK_STAR);
			++$count;
			if ($row != -1) {
				$path[$count] = array($row, $path[$count-1][1]);
			}
			else {
				$done = true;
			}
			if (!$done) {
				$col = $this->find_mark_in_row($path[$count][0],
					self::MASK_PRIME);
				++$count;
				$path[$count] = array($path[$count-1][0], $col);
			}
		}
		# Convert primes to stars and remove existing star marks
		for ($i = 0; $i < $count; ++$i) {
			list($row, $col) = $path[$i];
			if ($this->mask[$row][$col] == self::MASK_STAR) {
				$this->mask[$row][$col] = self::MASK_NONE;
			}
			else {
				$this->mask[$row][$col] = self::MASK_STAR;
			}
		}
		$this->uncover_all();
		# Remove all prime marks
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($this->mask[$i][$j] == self::MASK_PRIME) {
					$this->mask[$i][$j] = self::MASK_NONE;
				}
			}
		}
		return 'cover_star_cols';
	}
	
	/**
	 * Finds the minimum uncovered cost, adds it to the covered rows, and
	 * subtracts it from the uncovered columns. Returns the next method to
	 * call.
	 */
	public function balance_costs() {
		# Find the minimum uncovered cost
		$min_cost = $this->max_cost;
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($min_cost > $this->cost[$i][$j] &&
					!$this->row_cover[$i] &&
					!$this->col_cover[$j]) {
					$min_cost = $this->cost[$i][$j];
				}
			}
		}
		# Add and subtract the minimum cost from rows and columns
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($this->row_cover[$i]) {
					$this->cost[$i][$j] += $min_cost;
				}
				if (!$this->col_cover[$j]) {
					$this->cost[$i][$j] -= $min_cost;
				}
			}
		}
		return 'prime_zeros';
	}
	
	/**
	 * Uncovers all rows and columns.
	 */
	private function uncover_all() {
		$this->row_cover = array_fill(0, $this->num_rows, false);
		$this->col_cover = array_fill(0, $this->num_cols, false);
	}
	
	/**
	 * Returns the location of the first uncovered zero, or null if all
	 * zeros are covered.
	 */
	private function find_uncovered_zero() {
		for ($i = 0; $i < $this->num_rows; ++$i) {
			for ($j = 0; $j < $this->num_cols; ++$j) {
				if ($this->cost[$i][$j] <= 0 &&
					!$this->row_cover[$i] &&
					!$this->col_cover[$j]) {
					return array($i, $j);
				}
			}
		}
		return null;
	}
	
	/**
	 * Returns the index of the first cost with the given mark in the given
	 * row, or -1 if no costs are starred.
	 */
	private function find_mark_in_row($row, $mark) {
		for ($j = 0; $j < $this->num_cols; ++$j) {
			if ($this->mask[$row][$j] == $mark) {
				return $j;
			}
		}
		return -1;
	}
	
	/**
	 * Returns the index of the first cost with the given mark in the given
	 * column, or -1 if no costs are starred.
	 */
	private function find_mark_in_col($col, $mark) {
		for ($i = 0; $i < $this->num_rows; ++$i) {
			if ($this->mask[$i][$col] == $mark) {
				return $i;
			}
		}
		return -1;
	}
}

?>