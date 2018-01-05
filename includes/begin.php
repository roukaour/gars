<!DOCTYPE html>

<?php $gars = GARS::get_instance(); ?>

<html lang="en" dir="ltr">

<head>
<meta charset="<?php echo CHARSET; ?>">
<title><?php echo $this->get_title(); ?> | Graduate Application Review System</title>
<link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>includes/style.css">
<link rel="stylesheet" type="text/css" href="<?php echo BASE_URL; ?>includes/layout.css">
<link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>images/favicon.ico">
<?php if ($use_app_filters): ?>
<script src="<?php echo BASE_URL; ?>includes/app-filters.js"></script>
<?php endif; ?>
</head>

<body>

<div id="wrapper">

<div id="header">
<img src="<?php echo BASE_URL; ?>images/sbu-cs-logo.png" width="100" height="100">
<h1>Graduate Application<br>Review System</h1>
</div>

<div id="nav">
<ul>
<li><a href="<?php echo BASE_URL; ?>home.php">Home</a></li>
<li><a href="<?php echo BASE_URL; ?>applications.php">Applications</a></li>
<?php if ($gars->get_role() == 'chair'): ?>
<li><a href="<?php echo BASE_URL; ?>assignments.php">Review Assignments</a></li>
<li><a href="<?php echo BASE_URL; ?>upload.php">Upload Files</a></li>
<li><a href="<?php echo BASE_URL; ?>set-password.php">Set Password</a></li>
<?php else: ?>
<li><a href="<?php echo BASE_URL; ?>applications.php?m=y&u=y&a=f">Review Assignments</a></li>
<?php endif; ?>
</ul>
</div>

<?php if ($gars->is_logged_in()): ?>
<div id="user">
Logged in as <b><?php echo $gars->get_username(); ?></b><br>
<a href="<?php echo BASE_URL; ?>index.php?logout" rel="nofollow">Log out</a>
</div>
<?php endif; ?>

<div id="content">

<?php if ($gars->is_logged_in()): ?>
<h2><?php echo $this->get_title(); ?></h2>
<?php endif; ?>

<!--[if lte IE 6]>
<p class="center error"><big><b>Please update Internet Explorer or use a different browser.</b></big></p>
<![endif]-->