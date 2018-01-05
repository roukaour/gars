<?php

require_once 'init.php';

use_class('Page');

/**
 * This page outputs the data from the requested table as a TSV file.
 */
$page = new Page($_GET['table'] . '.tsv');

# Only the chair can access this page
$page->allow_only('chair');

# Act like a TSV file
header('Content-Type: text/tab-separated-values; charset=' . CHARSET);
header('Content-Disposition: attachment;filename=' . $page->get_title());

# Output the data
$gars = GARS::get_instance();
$bridge = $gars->get_bridge();
$result = $bridge->get_table_for_tsv($_GET['table']);
if (!$result) {
	die();
}
$num_rows = mysql_num_rows($result);
$handle = fopen('php://output', 'w');
for ($i = 0; $i < $num_rows; ++$i) {
	$row = mysql_fetch_assoc($result);
	if ($i == 0) {
		$headers = array_keys($row);
		fputcsv($handle, $headers, "\t");
	}
	fputcsv($handle, $row, "\t");
}
fclose($handle);

?>