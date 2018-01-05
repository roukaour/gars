<?php

require_once 'init.php';

use_class('Page');

/**
 * This page lets users review a single application.
 */
$page = new Page('Review ' . Text::html_encode($_GET['email']));

# Store response to requests, if any
$response = null;

# Respond to request to review application
if ($_POST['action'] == 'Enter review' || $_POST['action'] == 'Edit review') {
	$email = $_GET['email'];
	$rating = $_POST['rating'];
	$review = $_POST['review'];
	$gars = GARS::get_instance();
	$username = isset($_POST['username']) ? $_POST['username'] : $gars->get_username();
	$bridge = $gars->get_bridge();
	$response = $bridge->enter_review($email, $username, $rating, $review);
}

# Begin this page
$page->begin();

?>

<?php echo $response; ?>

<?php
$gars = GARS::get_instance();
$bridge = $gars->get_bridge();
$show_single_form = true;
if ($gars->get_role() == 'chair') {
	$reviews = $bridge->get_reviews($_GET['email']);
	$show_single_form = $reviews['hide'];
}
if ($show_single_form):
	$review = $bridge->get_review($_GET['email']);
?>

<?php
if (is_string($review)):
	echo $review;
else:
?>

<div class="aside">
<h5 class="no-bottom">Rating guidelines:</h5>
<ul>
<li>0.0&ndash;1.4: Good application; recommend admission</li>
<li>1.5&ndash;1.9: Some weaknesses but acceptable; recommend admission if we need more students</li>
<li>2.0&ndash;2.9: Significant but not necessarily fatal weaknesses; recommend rejection</li>
<li>3.0&ndash;6.0: Fatal weaknesses; strongly recommend rejection</li>
</ul>
</div>

<?php if (is_string($review['rating'])): ?>
<p>You already reviewed <b><a href="<?php echo BASE_URL; ?>application.php?email=<?php echo urlencode($_GET['email']); ?>">this application</a></b> at <?php echo Text::html_encode($review['date_formatted']); ?>, but you can edit your review.</p>
<?php else: ?>
<p>Read <b><a href="<?php echo BASE_URL; ?>application.php?email=<?php echo urlencode($_GET['email']); ?>">the application</a></b> and then enter your review.</p>
<?php endif; ?>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>?email=<?php echo urlencode($_GET['email']); ?>" method="post">

<p>
<label for="rating"><b>Rating:</b></label>
<input id="rating" name="rating" type="text" size="5" value="<?php echo is_string($review['rating']) ? (double)$review['rating'] : ''; ?>">
</p>

<p class="no-bottom"><label for="review"><b>Review:</b></label></p>
<textarea id="review" name="review" rows="4" cols="40"><?php echo Text::html_encode($review['review']); ?></textarea>

<p><input name="action" type="submit" value="<?php echo is_string($review['rating']) ? 'Edit review' : 'Enter review'; ?>"></p>

</form>

<?php endif; ?>

<?php elseif ($gars->get_role() == 'chair'): ?>

<div class="aside">
<h5 class="no-bottom">Rating guidelines:</h5>
<ul>
<li>0.0&ndash;1.4: Good application; recommend admission</li>
<li>1.5&ndash;1.9: Some weaknesses but acceptable; recommend admission if we need more students</li>
<li>2.0&ndash;2.9: Significant but not necessarily fatal weaknesses; recommend rejection</li>
<li>3.0&ndash;6.0: Fatal weaknesses; strongly recommend rejection</li>
</ul>
</div>

<p>You can edit any of the reviews for <b><a href="<?php echo BASE_URL; ?>application.php?email=<?php echo urlencode($_GET['email']); ?>">this application</a></b><?php echo $reviews['mine'] ? ', including your own' : ''; ?>.</p>

<?php
$num_made = count($reviews['made']);
if ($num_made == 0) {
	echo '<p>There are no reviews for this application.</p>';
}
?>

<p>
<?php
$num_pending = count($reviews['pending']);
for ($i = 0; $i < $num_pending; ++$i) {
	$username = $reviews['pending'][$i]['username'];
	echo '<b>', Text::html_encode($username), '</b>';
	if ($i < $num_pending - 1 && $num_pending > 2) {
		echo ', ';
	}
	if ($i == $num_pending - 2) {
		echo 'and ';
	}
}
if ($num_pending == 0) {
	echo 'There are no pending reviews for this application.';
}
elseif ($num_pending == 1) {
	echo ' has ';
}
else {
	echo ' have ';
}
if ($num_pending > 0) {
	echo 'yet to review this application.';
}
?>
</p>

<?php foreach ($reviews['made'] as $review): ?>

<h3>Review by <?php echo Text::html_encode($review['username']); ?></h3>

<p><b>Date reviewed:</b> <?php echo Text::html_encode($review['date_formatted']); ?></p>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>?email=<?php echo urlencode($_GET['email']); ?>" method="post">

<input name="username" type="hidden" value="<?php echo Text::html_encode($review['username']); ?>">

<p>
<label for="rating"><b>Rating:</b></label>
<input id="rating" name="rating" type="text" size="5" value="<?php echo is_string($review['rating']) ? (double)$review['rating'] : ''; ?>">
</p>

<p class="no-bottom"><label for="review"><b>Review:</b></label></p>
<textarea id="review" name="review" rows="4" cols="40"><?php echo Text::html_encode($review['review']); ?></textarea>

<p><input name="action" type="submit" value="Edit review"></p>

</form>

<?php endforeach; ?>

<?php
endif;
# End this page
$page->end();
?>