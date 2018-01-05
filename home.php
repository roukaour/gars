<?php

require_once 'init.php';

use_class('Page');

/**
 * This is the home page.
 */
$page = new Page('Home');

# Begin this page
$page->begin();

$gars = GARS::get_instance();
$bridge = $gars->get_bridge();

# Show any pending reviews
$pending = $bridge->get_pending_reviews();
if (is_string($pending)) {
	echo $pending;
}
else {
	$num_pending = mysql_num_rows($pending);
	if ($num_pending == 0) {
		echo '<p>You do not have any applications pending review.</p>';
	}
	elseif ($num_pending == 1) {
		$app = mysql_fetch_assoc($pending);
		echo '<p>You have an application from <b><a href="', BASE_URL, 'review.php?email=', urlencode($app['email_address']), '">', Text::html_encode($app['email_address']), '</a></b> pending review!</p>';
	}
	else {
		$num_items = min($num_pending, MAX_PENDING_PREVIEW);
		echo '<p class="no-bottom">You have <b><a href="', BASE_URL, 'applications.php?m=y&u=y&a=f">', $num_pending, ' applications</a></b> pending review! The oldest ones are:</p>', "\n<ul>\n";
		for ($i = 0; $i < $num_items; ++$i) {
			$app = mysql_fetch_assoc($pending);
			echo '<li><a href="', BASE_URL, 'review.php?email=', urlencode($app['email_address']), '">', Text::html_encode($app['email_address']), "</a></li>\n";
		}
		echo "</ul>\n";
	}
}

# Show any pending decisions
if ($gars->get_role() == 'chair') {
	$pending = $bridge->get_pending_decisions();
	if (is_string($pending)) {
		echo $pending;
	}
	else {
		$num_pending = mysql_num_rows($pending);
		if ($num_pending == 0) {
			echo '<p>There are no applications awaiting your decision.</p>';
		}
		elseif ($num_pending == 1) {
			$app = mysql_fetch_assoc($pending);
			echo '<p>There is an application from <b><a href="', BASE_URL, 'application.php?email=', urlencode($app['email_address']), '">', Text::html_encode($app['email_address']), '</a></b> with the number of desired reviews awaiting your decision!</p>';
		}
		else {
			$num_items = min($num_pending, MAX_PENDING_PREVIEW);
			echo '<p class="no-bottom">There are <b>', $num_pending, ' applications</b> with the number of desired reviews awaiting your decision! The oldest ones are:</p>', "\n<ul>\n";
			for ($i = 0; $i < $num_items; ++$i) {
				$app = mysql_fetch_assoc($pending);
				echo '<li><a href="', BASE_URL, 'application.php?email=', urlencode($app['email_address']), '">', Text::html_encode($app['email_address']), "</a></li>\n";
			}
			echo "</ul>\n";
		}
	}
}

# End this page
$page->end();

?>