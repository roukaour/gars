<?php

require_once 'init.php';

use_class('Page');

/**
 * This page lets the chair set a user's password. It can be used when creating
 * new users, or when a user has forgotten their password.
 */
$page = new Page('Set Password');

# Only the chair can access this page
$page->allow_only('chair');

# Store response to requests, if any
$response = null;

# Respond to request to set a user's password
if ($_POST['action'] == 'Set password') {
	$username = $_POST['username'];
	$password = $_POST['password'];
	$gars = GARS::get_instance();
	$creds = $gars->get_credentials_manager();
	$bridge = $gars->get_bridge();
	$response = $bridge->set_password($username, $password, $creds);
}

# Begin this page
$page->begin();

?>

<?php echo $response; ?>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>" method="post">

<p>Most user data can be added manually to the database. However, passwords are stored as hashed values along with a randomly-generated salt. Use this page to reset an existing user's password.</p>

<p>
<label for="username"><b>Username:</b></label>
<input name="username" type="text" size="30">
</p>

<p>
<label for="password"><b>Password:</b></label>
<input name="password" type="text" size="30">
</p>

<p><input name="action" type="submit" value="Set password"></p>

</form>

<?php
# End this page
$page->end();
?>