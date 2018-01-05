<?php

require_once 'init.php';

use_class('Page');

/**
 * This page lets users view applications.
 */
$page = new Page('Applications');

# Store response to requests, if any
$response = null;

# These will be accessed frequently
$gars = GARS::get_instance();
$bridge = $gars->get_bridge();

# Respond to request to search for an application
if ($_GET['a'] == 's') {
	$terms = $_GET['q'];
	$pg = $_GET['p'];
	$response = $bridge->search_applications($terms, $pg);
	# Go directly to application page if only one result
	if (is_array($response) && is_resource($response['result']) &&
		mysql_num_rows($response['result']) == 1) {
		$email = mysql_result($response['result'], 0, 'email_address');
		header('Location: ' . BASE_URL . 'application.php?email=' . urlencode($email));
		die();
	}
}
# Respond to request to filter applications
elseif ($_GET['a'] == 'f') {
	$filters = $_GET['f'];
	$sort = $_GET['s'];
	$only_mine = $_GET['m'];
	$unreviewed = $_GET['u'];
	$pg = $_GET['p'];
	$response = $bridge->filter_applications($filters, $sort, $only_mine,
		$unreviewed, $pg);
}
# No request was made, so just get all applications
else {
	$pg = $_GET['p'];
	$response = $bridge->get_applications($pg);
}

# Begin this page
$page->begin(true);

?>

<?php if ($gars->get_role() == 'chair'): ?>
<p>You can download the <a href="<?php echo BASE_URL; ?>tsv.php?table=applications">applications</a>, <a href="<?php echo BASE_URL; ?>tsv.php?table=reviews">reviews</a>, and <a href="<?php echo BASE_URL; ?>tsv.php?table=prior_reviews">prior reviews</a> as TSV files before removing old ones from the database.</p>
<?php endif; ?>

<fieldset>

<legend>Search/filter applications</legend>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>" method="get">
<p>Search for name or email: <input name="q" type="text" size="40">
<button name="a" type="submit" value="s">Search</button></p>
</form>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>" method="get">
<p class="no-bottom" style="line-height: 26px;">
<span id="app-filters">You need Javascript enabled to filter by criteria!</span>
<span id="app-rest"></span>
</p>
</form>

</fieldset>

<?php
if (is_string($response)):
	echo $response;
else:
?>
<?php
$current_page = $response['page'];
$max_page = $response['max'];
$app_results = $response['result'];
$num_rows = mysql_num_rows($app_results);
?>
<?php if ($num_rows == 0): ?>
<?php if ($_GET['a'] == 'Search'): ?>
<p class="error">No results found for "<b><?php echo Text::html_encode($_GET['q']); ?></b>"!</p>
<?php elseif ($_GET['a'] == 'Filter'): ?>
<p class="error">No results match that filter!</p>
<?php else: ?>
<p>There are no applications to display.</p>
<?php endif; ?>
<?php else: ?>

<?php if ($_GET['a'] == 'Search'): ?>
<p>Search results for "<b><?php echo Text::html_encode($_GET['q']); ?></b>":</p>
<?php elseif ($_GET['a'] == 'Filter'): ?>
<p><b>Filtered results:</b></p>
<?php endif; ?>

<?php
$query_string = preg_replace('/&p=\d+/', '', $_SERVER['QUERY_STRING']);
echo sprintf('<p>Page %d of %d.', $current_page, $max_page);
if ($current_page > 1 || $current_page < $max_page) {
	echo ' (';
}
if ($current_page > 1) {
	echo sprintf('<a href="%sapplications.php?%s&amp;p=%d">Previous</a>',
		BASE_URL,
		Text::html_encode($query_string),
		$current_page - 1
	);
}
if ($current_page > 1 && $current_page < $max_page) {
	echo ', ';
}
if ($current_page < $max_page) {
	echo sprintf('<a href="%sapplications.php?%s&amp;p=%d">Next</a>',
		BASE_URL,
		Text::html_encode($query_string),
		$current_page + 1);
}
if ($current_page > 1 || $current_page < $max_page) {
	echo ')';
}
echo '</p>';
?>

<div class="wide">
<table>

<tr>
<th>Name</th>
<th>Email</th>
<th>Research&nbsp;area</th>
<th>UG&nbsp;Inst.</th>
<th>UG&nbsp;GPA</th>
<th>Grad&nbsp;Inst.</th>
<th>Grad&nbsp;GPA</th>
<th>GRE</th>
<th>GRE&nbsp;Subj.</th>
<th>TOEFL</th>
<th>Tier</th>
<th>Ratings</th>
<th>PDF</th>
</tr>

<?php
for ($i = 0; $i < $num_rows; ++$i):
$row = mysql_fetch_assoc($app_results);
?>
<tr>
<td>
<a href="<?php echo BASE_URL; ?>application.php?email=<?php echo urlencode($row['email_address']); ?>"><?php echo Text::html_encode($row['last_name']); ?>,
<?php echo Text::html_encode($row['first_name']), is_string($row['middle_name']) ? '&nbsp;' . Text::html_encode($row['middle_name']) : ''; ?></a>
</td>
<td><?php echo str_replace('@', '@&#x200b;', Text::html_encode($row['email_address'])); ?></td>
<td><?php echo Text::html_encode($row['research_area_abbr']); echo is_string($row['research_topics']) ? ' (' . Text::html_encode($row['research_topics']) . ')' : ''; ?></td>
<td><?php echo Text::html_encode($row['ug_inst']); ?></td>
<td>
<?php echo $row['ug_GPA']; ?>
<?php echo is_string($row['ug_scale']) ? ' (' . Text::html_encode($row['ug_scale']) . ')' : ''; ?>
</td>
<td><?php echo Text::html_encode($row['grad_inst']); ?></td>
<td>
<?php echo $row['grad_GPA']; ?>
<?php echo is_string($row['grad_scale']) ? ' (' . Text::html_encode($row['grad_scale']) . ')' : ''; ?>
</td>
<td>
<?php echo is_string($row['ofcl_GRE_V']) ? 'V:&nbsp;' . (int)$row['ofcl_GRE_V'] : (is_string($row['GRE_V']) ? 'V:&nbsp;' . (int)$row['GRE_V'] : ''); ?><br>
<?php echo is_string($row['ofcl_GRE_Q']) ? 'Q:&nbsp;' . (int)$row['ofcl_GRE_Q'] : (is_string($row['GRE_Q']) ? 'Q:&nbsp;' . (int)$row['GRE_Q'] : ''); ?><br>
<?php echo is_string($row['ofcl_GRE_A']) ? 'A:&nbsp;' . (double)$row['ofcl_GRE_A'] : (is_string($row['GRE_A']) ? 'A:&nbsp;' . (double)$row['GRE_A'] : ''); ?>
</td>
<td><?php echo is_string($row['ofcl_GRE_subj_name']) ? Text::html_encode($row['ofcl_GRE_subj_name']) . ': ' : (is_string($row['GRE_subj_name']) ? Text::html_encode($row['GRE_subj_name']) . ': ' : ''), is_string($row['ofcl_GRE_subj']) ? (int)$row['ofcl_GRE_subj'] : (is_string($row['GRE_subj_score']) ? (int)$row['GRE_subj_score'] : ''); ?></td>
<td>
<?php echo is_string($row['ofcl_TOEFL_total']) ? (int)$row['ofcl_TOEFL_total'] : (is_string($row['TOEFL']) ? (int)$row['TOEFL'] : ''); ?>
<?php echo is_string($row['ofcl_TOEFL_listen']) ? '<br>L:&nbsp;' . (int)$row['ofcl_TOEFL_listen'] : ''; ?>
<?php echo is_string($row['ofcl_TOEFL_read']) ? '<br>R:&nbsp;' . (int)$row['ofcl_TOEFL_read'] : ''; ?>
<?php echo is_string($row['ofcl_TOEFL_speak']) ? '<br>S:&nbsp;' . (int)$row['ofcl_TOEFL_speak'] : ''; ?>
<?php echo is_string($row['ofcl_TOEFL_write']) ? '<br>W:&nbsp;' . (int)$row['ofcl_TOEFL_write'] : ''; ?>
</td>
<td><?php echo (double)$row['tier'] == -1.0 ? '' : (double)$row['tier']; ?></td>
<td><?php echo $row['reviewed'] === '0' ? '<i>Hidden</i>' : (is_string($row['ratings']) ?  Text::html_encode($row['ratings']) . ' (avg ' . (double)$row['avgRating'] . ')' : ''); ?></td>
<td><?php $url = $bridge->pdf_url($row['email_address']); echo is_string($url) ? '<a href="' . $url . '">PDF</a>' : ''; ?></td>
</tr>
<?php endfor; ?>

</table>
</div>

<?php
endif;
endif;
# End this page
$page->end();
?>