<?php

require_once 'init.php';

use_class('Page');

/**
 * This page lets the chair upload applications.
 */
$page = new Page('Upload Application Files');

# Only the chair can access this page
$page->allow_only('chair');

# Store response to requests, if any
$response = null;

# Respond to request to upload files
if ($_POST['action'] == 'Upload file') {
	$file = $_FILES['file'];
	$type = $_POST['type'];
	$program = $_POST['program'];
	$gars = GARS::get_instance();
	$bridge = $gars->get_bridge();
	$response = $bridge->upload_file($file, $type, $program);
}

# Begin this page
$page->begin();

?>

<?php echo $response; ?>

<form action="<?php echo Text::html_encode($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">

<?php
$max_file_size = min(ini_int(ini_get('post_max_size')), ini_int(ini_get('upload_max_filesize')));
echo '<input type="hidden" name="MAX_FILE_SIZE" value="', $max_file_size, '">';
?>

<p>
<label for="file"><b>File:</b></label>
<input id="file" name="file" type="file" size="30">
</p>

<p>
<b>Type:</b>
<input id="type-ay" name="type" type="radio" value="AY">
<label for="type-ay">ApplyYourself form (AY)</label>
<input id="type-ots" name="type" type="radio" value="OTS">
<label for="type-ots">Official Test Scores (OTS)</label>
<input id="type-pdf" name="type" type="radio" value="PDF">
<label for="type-pdf">Zipped PDFs</label>
</p>

<p>
<b>Program:</b>
<input id="program-ms" name="program" type="radio" value="M.S.">
<label for="program-ms">M.S.</label>
<input id="program-phd" name="program" type="radio" value="Ph.D.">
<label for="program-phd">Ph.D.</label>
</p>

<p><input name="action" type="submit" value="Upload file"></p>

</form>

<?php
# End this page
$page->end();
?>