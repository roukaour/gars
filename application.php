<?php

require_once 'init.php';

use_class('Page');

/**
 * This page lets users view a single application.
 */
$page = new Page('Application for ' . Text::html_encode($_GET['email']));

# Store response to requests, if any
$response = null;

# Respond to request to edit decision data
if ($_POST['action'] == 'Update decision') {
	$email = $_GET['email'];
	$desired_reviews = $_POST['desired-reviews'];
	$tier = $_POST['tier'];
	$summary = $_POST['summary'];
	$toefl_comments = $_POST['toefl-comments'];
	$gars = GARS::get_instance();
	$bridge = $gars->get_bridge();
	$response = $bridge->update_decision_data($email, $desired_reviews, $tier, $summary, $toefl_comments);
}
# Respond to request to edit SBU data
elseif ($_POST['action'] == 'Edit SBU data') {
	$email = $_GET['email'];
	$sbu_id = $_POST['sbu-id'];
	$sbu_gpa = $_POST['sbu-gpa'];
	$gars = GARS::get_instance();
	$bridge = $gars->get_bridge();
	$response = $bridge->edit_sbu_data($email, $sbu_id, $sbu_gpa);
}

# Begin this page
$page->begin();

?>

<?php echo $response; ?>

<?php
$gars = GARS::get_instance();
$bridge = $gars->get_bridge();
$app = $bridge->get_application($_GET['email']);
if (is_string($app)):
	echo $app;
else:
?>

<?php
$reviews = $bridge->get_reviews($app['email_address']);
$num_made = count($reviews['made']);
$num_pending = count($reviews['pending']);
$num_reviews = $num_made + $num_pending;
$is_chair = $gars->get_role() == 'chair';
if ($reviews['hide']):
?>
<p class="alert">You have been assigned this application to review! Read it carefully, then <b><a href="<?php echo BASE_URL; ?>review.php?email=<?php echo urlencode($app['email_address']); ?>">enter your review</a></b>.</p>
<?php elseif ($is_chair && $num_reviews > 0): ?>
<p>You can <b><a href="<?php echo BASE_URL; ?>review.php?email=<?php echo urlencode($app['email_address']); ?>">edit the reviews</a></b> for this application.</p>
<?php elseif ($reviews['mine']): ?>
<p>You have already reviewed this application, but you can <b><a href="<?php echo BASE_URL; ?>review.php?email=<?php echo urlencode($app['email_address']); ?>">edit your review</a></b>.</p>
<?php endif; ?>

<p><b>Degree program:</b> <?php echo Text::html_encode($app['degreeProgram']); ?></p>
<p><b>Full PDF:</b> <?php $url = $bridge->pdf_url($app['email_address']);
echo is_string($url) ? '<a href="' . $url . '">' . Text::html_encode($app['email_address']) . '.pdf</a>' : '<i>Not avaliable</i>'; ?></p>

<h3>Reviews</h3>

<?php if ($reviews['hide']): ?>
<p>Reviews are hidden until you review this application.</p>
<?php elseif ($num_made > 0): ?>
<p class="no-bottom"><b>Reviews:</b> <?php echo $num_made, $num_pending > 0 ? ' (' . $num_pending . ' pending)' : ''; ?>; average rating <b><?php echo (double)$app['avgRating']; ?></b></p>
<ul>
<?php
foreach ($reviews['made'] as $review) {
	echo '<li>Rated <b>', (double)$review['rating'], '</b>', ($is_chair ? ' by <b>' . Text::html_encode($review['username']) . '</b>' : ''), ' at ', Text::html_encode($review['date_formatted']), ':<br>', Text::html_encode($review['review']), '</li>', "\n";
}
?>
</ul>
<?php else: ?>
<p><b>Reviews:</b> <i>None</i> <?php echo $num_pending > 0 ? ' (' . $num_pending . ' pending)' : ''; ?></p>
<?php endif; ?>

<?php if ($is_chair): ?>
<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>?email=<?php echo urlencode($_GET['email']); ?>" method="post">
<fieldset>
<legend>Review decision</legend>
<div class="aside">
<h5 class="no-bottom">Tier guidelines:</h5>
<ul>
<li>0.0–1.0: Admit</li>
<li>1.1–1.4: Very likely admit; need interview or additional documents</li>
<li>1.5–1.9: Borderline; need additional review</li>
<li>2.0–2.9: Likely reject, but can be revived</li>
<li>3.0–6.0: Reject</li>
</ul>
</div>
<p>
<label for="desired-reviews"><b>Desired reviews:</b></label>
<input id="desired-reviews" name="desired-reviews" size="5" value="<?php echo (int)$app['numDesiredReviews']; ?>">
</p>
<p>
<label for="tier"><b>Tier:</b></label>
<input id="tier" name="tier" size="5" value="<?php echo $app['tier'] == -1.0 ? '' : (double)$app['tier'];?>">
</p>
<p class="no-bottom"><label for="summary"><b>Summary:</b></label></p>
<textarea id="summary" name="summary" rows="3" cols="40"><?php echo Text::html_encode($app['summary']); ?></textarea>
<p class="no-bottom"><label for="toefl-comments"><b>TOEFL comments:</b></label></p>
<textarea id="toefl-comments" name="toefl-comments" rows="3" cols="40"><?php echo Text::html_encode($app['TOEFLcomments']); ?></textarea>
<p><input name="action" type="submit" value="Update decision"></p>
</fieldset>
</form>
<?php else: ?>
<p><b>Tier:</b> <?php echo (double)$app['tier'] == -1.0 ? '<i>Not yet rated</i>' : (double)$app['tier']; ?></p>
<p><b>Summary:</b> <?php echo is_string($app['summary']) ? Text::html_encode($app['summary']) : '<i>None</i>'; ?></p>
<p><b>TOEFL comments:</b> <?php echo is_string($app['TOEFLcomments']) ? Text::html_encode($app['TOEFLcomments']) : '<i>None</i>'; ?></p>
<?php endif; ?>

<h3>AY/OTS Data</h3>

<p><b>Date submitted:</b> <?php echo is_string($app['submission_date']) ? Text::html_encode($app['submission_date_formatted']) : '<i>Not specified</i>'; ?></p>
<p><b>Date uploaded:</b> <?php echo Text::html_encode($app['uploadDate_formatted']); ?></p>

<table class="no-border left">
<colgroup>
<col width="50%">
<col width="50%">
</colgroup>
<tr>
<td>

<fieldset>
<legend>Personal</legend>
<p><b>Client ID:</b> <?php echo is_string($app['client_ID']) ? Text::html_encode($app['client_ID']) : '<i>Not specified</i>'; ?></p>
<p><b>Name:</b> <?php echo Text::html_encode($app['first_name']), ' ', Text::html_encode($app['middle_name']), ' ', Text::html_encode($app['last_name']); ?></p>
<p><b>Date of birth:</b> <?php echo str_pad($app['birth_month'], 2, '0', STR_PAD_LEFT), '/', str_pad($app['birth_day'], 2, '0', STR_PAD_LEFT), '/', (int)$app['birth_year']; ?></p>
<p><b>Gender:</b> <?php echo is_string($app['gender']) ? Text::format_gender($app['gender']) : '<i>Not specified</i>'; ?></p>
<p><b>Ethnicity:</b> <?php echo is_string($app['ethnicity']) ? Text::html_encode($app['ethnicity']) : '<i>Not specified</i>'; ?></p>
<p><b>Race:</b> <?php echo is_string($app['race']) ? Text::html_encode($app['race']) : '<i>Not specified</i>'; ?></p>
<p><b>Country of citizenship:</b> <?php echo is_string($app['citizenship']) ? Text::html_encode($app['citizenship_country_name']) : '<i>Not specified</i>'; ?> (<?php echo $app['permanent_resident'] ? 'permanent resident' : 'not permanent resident'; ?>)</p>
<p><b>Phone number:</b> <?php echo is_string($app['phone']) ? Text::format_phone($app['phone']) : '<i>Not specified</i>'; ?></p>
<p><b>Email address:</b> <?php echo Text::html_encode($app['email_address']); ?></p>
<p><b>Specialization:</b> <?php echo is_string($app['specialization']) ? Text::html_encode($app['specialization']) : '<i>Not specified</i>'; ?></p>
<p><b>Research area:</b> <?php echo is_string($app['research_area']) ? Text::html_encode($app['research_area_name']) : '<i>Not specified</i>'; ?> <?php echo is_string($app['research_topics']) ? '(' . Text::html_encode($app['research_topics']) . ')' : ''; ?></p>
<?php echo is_string($app['otherInfo']) ? '<p class="no-bottom"><b>Other info:</b></p>' . Text::format_other_info($app['otherInfo']) : '<p><b>Other info:</b> <i>None</i></p>'; ?>
</fieldset>

<fieldset>
<legend>Undergraduate</legend>
<p><b>Institution:</b> <?php echo is_string($app['ug_inst']) ? Text::html_encode($app['ug_inst']) : '<i>Not specified</i>'; ?></p>
<p><b>Rank:</b> <?php echo is_string($app['ug_rank']) ? Text::ordinal((int)$app['ug_rank']) : '<i>Not specified</i>'; echo is_string($app['ug_out_of']) ? ' out of ' . (int)$app['ug_out_of'] : ''; ?></p>
<p class="no-bottom"><b>GPA:</b> <?php echo is_string($app['ug_GPA']) ? (double)$app['ug_GPA'] : '<i>Not specified</i>'; echo is_string($app['ug_scale']) ? ' (' . Text::html_encode($app['ug_scale']) . ')' : ''; ?></p>
<ul>
<li><b>Year 1:</b> <?php echo is_string($app['ug_GPA1']) ? (double)$app['ug_GPA1'] : '<i>Not specified</i>'; ?></li>
<li><b>Year 2:</b> <?php echo is_string($app['ug_GPA2']) ? (double)$app['ug_GPA2'] : '<i>Not specified</i>'; ?></li>
<li><b>Year 3:</b> <?php echo is_string($app['ug_GPA3']) ? (double)$app['ug_GPA3'] : '<i>Not specified</i>'; ?></li>
<li><b>Year 4:</b> <?php echo is_string($app['ug_GPA4']) ? (double)$app['ug_GPA4'] : '<i>Not specified</i>'; ?></li>
<li><b>Year 5:</b> <?php echo is_string($app['ug_GPA5']) ? (double)$app['ug_GPA5'] : '<i>Not specified</i>'; ?></li>
</ul>
</fieldset>

<fieldset>
<legend>Graduate</legend>
<p><b>Institution:</b> <?php echo is_string($app['grad_inst']) ? Text::html_encode($app['grad_inst']) : '<i>Not specified</i>'; ?></p>
<p><b>Rank:</b> <?php echo is_string($app['grad_rank']) ? Text::ordinal((int)$app['grad_rank']) : '<i>Not specified</i>'; echo is_string($app['grad_out_of']) ? ' out of ' . (int)$app['grad_out_of'] : ''; ?></p>
<p class="no-bottom"><b>GPA:</b> <?php echo is_string($app['grad_GPA']) ? (double)$app['grad_GPA'] : '<i>Not specified</i>'; echo is_string($app['grad_scale']) ? ' (' . Text::html_encode($app['grad_scale']) . ')' : ''; ?></p>
<ul>
<li><b>Year 1:</b> <?php echo is_string($app['grad_GPA1']) ? (double)$app['grad_GPA1'] : '<i>Not specified</i>'; ?></li>
<li><b>Year 2:</b> <?php echo is_string($app['grad_GPA2']) ? (double)$app['grad_GPA2'] : '<i>Not specified</i>'; ?></li>
</ul>
</fieldset>

</td>
<td>

<fieldset>
<legend>Self-Reported Test Scores</legend>
<p class="no-bottom"><b>GRE:</b></p>
<ul>
<li><b>Verbal:</b> <?php echo is_string($app['GRE_V']) ? (int)$app['GRE_V'] : '<i>Not specified</i>'; echo is_string($app['GRE_V_pctile']) ? ' (' . Text::ordinal((int)$app['GRE_V_pctile']) . ' percentile)' : ''; ?></li>
<li><b>Quantitative:</b> <?php echo is_string($app['GRE_Q']) ? (int)$app['GRE_Q'] : '<i>Not specified</i>'; echo is_string($app['GRE_Q_pctile']) ? ' (' . Text::ordinal((int)$app['GRE_Q_pctile']) . ' percentile)' : ''; ?></li>
<li><b>Analytical:</b> <?php echo is_string($app['GRE_A']) ? (double)$app['GRE_A'] : '<i>Not specified</i>'; echo is_string($app['GRE_A_pctile']) ? ' (' . Text::ordinal((int)$app['GRE_A_pctile']) . ' percentile)' : ''; ?></li>
</ul>
<p><b>GRE Subject:</b> <?php echo is_string($app['GRE_subj_name']) ? Text::html_encode($app['GRE_subj_name']) : '<i>Not specified</i>'; echo is_string($app['GRE_subj_score']) ? ': ' . (int)$app['GRE_subj_score'] : ''; ?></p>
<p><b>TOEFL:</b> <?php echo is_string($app['TOEFL']) ? (int)$app['TOEFL'] : '<i>Not specified</i>'; ?></p>
<p><b>TOEFL Internet:</b> <?php echo is_string($app['TOEFL_internet']) ? (int)$app['TOEFL_internet'] : '<i>Not specified</i>'; ?></p>
<p><b>IELTS:</b> <?php echo is_string($app['IELTS']) ? (double)$app['IELTS'] : '<i>Not specified</i>'; ?></p>
</fieldset>

<fieldset>
<legend>Official Test Scores</legend>
<p class="no-bottom"><b>GRE:</b></p>
<ul>
<li><b>Verbal:</b> <?php echo is_string($app['ofcl_GRE_V']) ? (int)$app['ofcl_GRE_V'] : '<i>Not specified</i>'; echo is_string($app['ofcl_GRE_V_pctile']) ? ' (' . Text::ordinal((int)$app['ofcl_GRE_V_pctile']) . ' percentile)' : ''; ?></li>
<li><b>Quantitative:</b> <?php echo is_string($app['ofcl_GRE_Q']) ? (int)$app['ofcl_GRE_Q'] : '<i>Not specified</i>'; echo is_string($app['ofcl_GRE_Q_pctile']) ? ' (' . Text::ordinal((int)$app['ofcl_GRE_Q_pctile']) . ' percentile)' : ''; ?></li>
<li><b>Analytical:</b> <?php echo is_string($app['ofcl_GRE_A']) ? (double)$app['ofcl_GRE_A'] : '<i>Not specified</i>'; echo is_string($app['ofcl_GRE_A_pctile']) ? ' (' . Text::ordinal((int)$app['ofcl_GRE_A_pctile']) . ' percentile)' : ''; ?></li>
</ul>
<p><b>GRE Subject:</b> <?php echo is_string($app['ofcl_GRE_subj_name']) ? Text::html_encode($app['ofcl_GRE_subj_name']) : '<i>Not specified</i>', is_string($app['ofcl_GRE_subj']) ? ': ' . (int)$app['ofcl_GRE_subj'] : ''; ?></p>
<p class="no-bottom"><b>TOEFL:</b> <?php echo is_string($app['ofcl_TOEFL_total']) ? (int)$app['ofcl_TOEFL_total'] : '<i>Not specified</i>'; ?></p>
<ul>
<li><b>Listening:</b> <?php echo is_string($app['ofcl_TOEFL_listen']) ? (int)$app['ofcl_TOEFL_listen'] : '<i>Not specified</i>'; ?></li>
<li><b>Reading:</b> <?php echo is_string($app['ofcl_TOEFL_read']) ? (int)$app['ofcl_TOEFL_read'] : '<i>Not specified</i>'; ?></li>
<li><b>Speaking:</b> <?php echo is_string($app['ofcl_TOEFL_speak']) ? (int)$app['ofcl_TOEFL_speak'] : '<i>Not specified</i>'; ?></li>
<li><b>Writing:</b> <?php echo is_string($app['ofcl_TOEFL_write']) ? (int)$app['ofcl_TOEFL_write'] : '<i>Not specified</i>'; ?></li>
</ul>
</fieldset>

<fieldset>
<legend>CS Proficency</legend>
<p><b>Theory of Computation:</b> <?php echo is_string($app['theory_course_title']) ? Text::html_encode($app['theory_course_title']) : '<i>Not specified</i>'; echo is_string($app['theory_SBU_equiv']) ? ' (SBU equiv: ' . Text::html_encode($app['theory_SBU_equiv']) . ')' : ''; ?>: <?php echo is_string($app['theory_grade']) ? Text::html_encode($app['theory_grade']) : '<i>Not specified</i>'; echo is_string($app['theory_scale']) ? ' (' . Text::html_encode($app['theory_scale']) . ')' : ''; ?></p>
<p><b>Algorithms:</b> <?php echo is_string($app['algorithm_course_title']) ? Text::html_encode($app['algorithm_course_title']) : '<i>Not specified</i>'; echo is_string($app['algorithm_SBU_equiv']) ? ' (SBU equiv: ' . Text::html_encode($app['algorithm_SBU_equiv']) . ')' : ''; ?>: <?php echo is_string($app['algorithm_grade']) ? Text::html_encode($app['algorithm_grade']) : '<i>Not specified</i>'; echo is_string($app['algorithm_scale']) ? ' (' . Text::html_encode($app['algorithm_scale']) . ')' : ''; ?></p>
<p><b>Languages/Compilers:</b> <?php echo is_string($app['prog_lang_course_title']) ? Text::html_encode($app['prog_lang_course_title']) : '<i>Not specified</i>'; echo is_string($app['prog_lang_SBU_equiv']) ? ' (SBU equiv: ' . Text::html_encode($app['prog_lang_SBU_equiv']) . ')' : ''; ?>: <?php echo is_string($app['prog_lang_grade']) ? Text::html_encode($app['prog_lang_grade']) : '<i>Not specified</i>'; echo is_string($app['prog_lang_scale']) ? ' (' . Text::html_encode($app['prog_lang_scale']) . ')' : ''; ?></p>
<p><b>Operating Systems:</b> <?php echo is_string($app['os_course_title']) ? Text::html_encode($app['os_course_title']) : '<i>Not specified</i>'; echo is_string($app['os_SBU_equiv']) ? ' (SBU equiv: ' . Text::html_encode($app['os_SBU_equiv']) . ')' : ''; ?>: <?php echo is_string($app['os_grade']) ? Text::html_encode($app['os_grade']) : '<i>Not specified</i>'; echo is_string($app['os_scale']) ? ' (' . Text::html_encode($app['os_scale']) . ')' : ''; ?></p>
</fieldset>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>?email=<?php echo urlencode($_GET['email']); ?>" method="post">
<fieldset>
<legend>SBU Data</legend>
<p>
<label for="sbu-id"><b>ID:</b></label>
<input id="sbu-id" name="sbu-id" size="15" value="<?php echo Text::html_encode($app['SBU_ID']); ?>">
</p>
<p>
<label for="sbu-gpa"><b>GPA:</b></label>
<input id="sbu-gpa" name="sbu-gpa" size="5" value="<?php echo is_string($app['SBU_GPA']) ? (double)$app['SBU_GPA'] : ''; ?>">
</p>
<p><input name="action" type="submit" value="Edit SBU data"></p>
</fieldset>
</form>

</td>
</tr>
</table>

<?php
endif;
# End this page
$page->end();
?>