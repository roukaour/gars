<?php

require_once 'init.php';

use_class('Page');

/**
 * This page acts as a user filter. Logged-in users are redirected to home.php;
 * otherwise they are shown the login form. This page also handles logging in
 * (via a POST request) and out (via a GET request).
 */
$page = new Page('Log in', true);

$gars = GARS::get_instance();

# Respond to login request
if ($_POST['action'] == 'Log in') {
	$username = $_POST['username'];
	$password = $_POST['password'];
	$gars->login($username, $password);
}
# Respond to logout request
else if (isset($_GET['logout'])) {
	$gars->logout();
}

# Redirect logged-in users to home page
if ($gars->is_logged_in()) {
	header('Location: ' . BASE_URL . 'home.php');
	die();
}

# Begin this page
$page->begin();

?>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>" method="post">

<table id="login" class="no-border">
<?php if (isset($_GET['denied'])): ?>
<tr>
<td colspan="2" class="center error">You are not logged in!</td>
</tr>
<?php elseif ($_POST['action'] == 'Log in'): ?>
<tr>
<td colspan="2" class="center error">Incorrect username or password!</td>
</tr>
<?php endif; ?>
<tr>
<th><label for="username">Username:</label></th>
<td><input name="username" type="text" size="30"></td>
</tr>
<tr>
<th><label for="password">Password:</label></th>
<td><input name="password" type="password" size="30"></td>
</tr>
<tr>
<th><input name="action" type="submit" value="Log in"></th>
<td></td>
</tr>
</table>

</form>

<?php
# End this page
$page->end();
?>