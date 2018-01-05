<?php

require_once 'init.php';

use_class('Page');

/**
 * This page allows the chair to assign reviewers to applications.
 */
$page = new Page('Review Assignments');

# Only the chair can access this page
$page->allow_only('chair');

# Store response to requests, if any
$response = null;

$gars = GARS::get_instance();
$bridge = $gars->get_bridge();

# Respond to request to assign application to reviewer
if ($_POST['action'] == 'Assign') {
	$username = $_POST['username'];
	$email = $_POST['email'];
	$response = $bridge->assign_review($username, $email);
}
# Respond to request to unassign application from reviewer
elseif ($_POST['action'] == 'Unassign') {
	$username = $_POST['username'];
	$email = $_POST['email'];
	$response = $bridge->unassign_review($username, $email);
}
# Respond to request to automatically assign applications
elseif ($_POST['action'] == 'Auto-assign reviewers') {
	$response = $bridge->auto_assign_reviews();
}
# Respond to request to increment numDesiredReviews
elseif ($_POST['action'] == 'Increment desired reviews') {
	$response = $bridge->increment_desired_reviews();
}

# Begin this page
$page->begin();

?>

<?php echo $response; ?>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>" method="post">

<p>You can manually assign or unassign an application:</p>

<p>
<label for="username"><b>Reviewer username:</b></label>
<input id="username" name="username" size="30">
</p>

<p>
<label for="email"><b>Applicant email:</b></label>
<input id="email" name="email" size="30">
</p>

<p>
<input name="action" type="submit" value="Assign">
<input name="action" type="submit" value="Unassign">
</p>

<p>GARS can automatically assign reviewers to applications with fewer than the number of desired reviews, taking into account the reviewers' workloads and countries/research areas of expertise:</p>

<p><input name="action" type="submit" value="Auto-assign reviewers"></p>

<p>If some applications have met their number of desired reviews but are still undecided (i.e. their tier is &minus;1 or between 1.5 and 1.9), you can increment their number of desired reviews:</p>

<p><input name="action" type="submit" value="Increment desired reviews"></p>

</form>

<?php
# End this page
$page->end();
?>