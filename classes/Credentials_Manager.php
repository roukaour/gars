<?php

/**
 * This class handles the details of creating and verifying user credentials.
 * It associates each user with a hash and unique random salt.
 */
class Credentials_Manager {
	# The type of hash used for passwords
	private $hash_type;
	# The number of bytes in a generated salt
	private $salt_bytes;
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->hash_type = HASH_TYPE;
		$this->salt_bytes = SALT_BITS / 8;
	}
	
	/**
	 * Returns a random salt.
	 */
	public function generate_salt() {
		$salt = '';
		for ($i = 0; $i < $this->salt_bytes; ++$i) {
			$salt .= chr(mt_rand(0, 255));
		}
		return $salt;
	}
	
	/**
	 * Returns a hash of the given password and salt.
	 */
	public function hash($password, $salt) {
		return hash($this->hash_type, $password . $salt, true);
	}
	
	/**
	 * Returns whether the given password and salt hash to the given hash.
	 */
	public function is_correct($password, $salt, $correct_hash) {
		$hash = $this->hash($password, $salt);
		return $hash == $correct_hash;
	}
}

?>