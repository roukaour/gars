var app_filters_id = 'app-filters';
var app_rest_id = 'app-rest';

// The ID of the last filter added
var last_id = 0;
// The total number of filters added
var num_filters = 0;
// The maximum number of filters that can be added
var max_filters = 5;

/**
 * Returns a string for an HTML <select> element with the given ID, to select
 * columns in the applications database.
 */
function select_field(id) {
	return '<select name="' + id + '">' +
	'<option value="">Field&hellip;</option>' +
	'<option value="tier">Tier</option>' +
	'<option value="summary">Summary</option>' +
	'<option value="TOEFLcomments">TOEFL comments</option>' +
	'<option value="avgRating">Average rating</option>' +
	'<option value="degreeProgram">Degree program</option>' +
	'<option value="client_ID">Client ID</option>' +
	'<option value="first_name">First name</option>' +
	'<option value="middle_name">Middle name</option>' +
	'<option value="last_name">Last name</option>' +
	'<option value="birth_month">Birth month</option>' +
	'<option value="birth_day">Birth day</option>' +
	'<option value="birth_year">Birth year</option>' +
	'<option value="gender">Gender</option>' +
	'<option value="ethnicity">Ethnicity</option>' +
	'<option value="race">Race</option>' +
	'<option value="citizenship">Country of citizenship</option>' +
	'<option value="permanent_resident">Permanent resident?</option>' +
	'<option value="phone">Phone number</option>' +
	'<option value="email_address">Email address</option>' +
	'<option value="specialization">Specialization</option>' +
	'<option value="research_area">Research area</option>' +
	'<option value="research_topics">Research topics</option>' +
	'<option value="ug_inst">UG institution</option>' +
	'<option value="ug_GPA">UG GPA</option>' +
	'<option value="ug_scale">UG grading scale</option>' +
	'<option value="ug_GPA1">UG GPA year 1</option>' +
	'<option value="ug_GPA2">UG GPA year 2</option>' +
	'<option value="ug_GPA3">UG GPA year 3</option>' +
	'<option value="ug_GPA4">UG GPA year 4</option>' +
	'<option value="ug_GPA5">UG GPA year 5</option>' +
	'<option value="ug_rank">UG rank</option>' +
	'<option value="ug_out_of">UG rank out of</option>' +
	'<option value="grad_inst">Grad institution</option>' +
	'<option value="grad_GPA">Grad GPA</option>' +
	'<option value="grad_scale">Grad grading scale</option>' +
	'<option value="grad_GPA1">Grad GPA year 1</option>' +
	'<option value="grad_GPA2">Grad GPA year 2</option>' +
	'<option value="grad_rank">Grad rank</option>' +
	'<option value="grad_out_of">Grad rank out of</option>' +
	'<option value="theory_course_title">Theory course name</option>' +
	'<option value="theory_scale">Theory grading scale</option>' +
	'<option value="theory_grade">Theory grade</option>' +
	'<option value="theory_SBU_equiv">Theory SBU equiv.</option>' +
	'<option value="algorithm_course_title">Algorithims course name</option>' +
	'<option value="algorithm_scale">Algorithims grading scale</option>' +
	'<option value="algorithm_grade">Algorithims grade</option>' +
	'<option value="algorithm_SBU_equiv">Algorithims SBU equiv.</option>' +
	'<option value="prog_course_title">Prog. course name</option>' +
	'<option value="prog_scale">Prog. grading scale</option>' +
	'<option value="prog_grade">Prog. grade</option>' +
	'<option value="prog_SBU_equiv">Prog. SBU equiv.</option>' +
	'<option value="os_course_title">OS course name</option>' +
	'<option value="os_scale">OS grading scale</option>' +
	'<option value="os_grade">OS grade</option>' +
	'<option value="os_SBU_equiv">OS SBU equiv.</option>' +
	'<option value="GRE_V">GRE Verbal score</option>' +
	'<option value="GRE_V_pctile">GRE Verbal pct.</option>' +
	'<option value="GRE_Q">GRE Quantitative score</option>' +
	'<option value="GRE_Q_pctile">GRE Quantitative pct.</option>' +
	'<option value="GRE_A">GRE Analytical score</option>' +
	'<option value="GRE_A_pctile">GRE Analytical pct.</option>' +
	'<option value="GRE_subj_name">GRE Subject name</option>' +
	'<option value="GRE_subj_score">GRE Subject score</option>' +
	'<option value="TOEFL">TOEFL score</option>' +
	'<option value="TOEFL_internet">TOEFL Internet score</option>' +
	'<option value="IELTS">IELTS score</option>' +
	'<option value="ofcl_GRE_V">Ofcl. GRE Verbal score</option>' +
	'<option value="ofcl_GRE_V_pctile">Ofcl. GRE Verbal pct.</option>' +
	'<option value="ofcl_GRE_Q">Ofcl. GRE Quantitative score</option>' +
	'<option value="ofcl_GRE_Q_pctile">Ofcl. GRE Quantitative pct.</option>' +
	'<option value="ofcl_GRE_A">Ofcl. GRE Analytical score</option>' +
	'<option value="ofcl_GRE_A_pctile">Ofcl. GRE Analytical pct.</option>' +
	'<option value="ofcl_GRE_subj">Ofcl. GRE Subject score</option>' +
	'<option value="ofcl_GRE_subj_pctile">Ofcl. GRE Subject pct.</option>' +
	'<option value="ofcl_GRE_subj_name">Ofcl. GRE Subject name</option>' +
	'<option value="ofcl_TOEFL_total">Ofcl. TOEFL score</option>' +
	'<option value="ofcl_TOEFL_listen">Ofcl. TOEFL Listening score</option>' +
	'<option value="ofcl_TOEFL_read">Ofcl. TOEFL Reading score</option>' +
	'<option value="ofcl_TOEFL_speak">Ofcl. TOEFL Speaking score</option>' +
	'<option value="ofcl_TOEFL_write">Ofcl. TOEFL Writing score</option>' +
	'<option value="SBU_ID">SBU ID</option>' +
	'<option value="SBU_GPA">SBU GPA</option>' +
	'<option value="otherInfo">Other info</option>' +
	'</select>';
}

/**
 * Returns a string for an HTML <select> element with the given ID, to select
 * relations between database columns and their values.
 */
function select_relation(id) {
	return '<select name="' + id + '">' +
	'<option value="is">is</option>' +
	'<option value="in">contains</option>' +
	'<option value="ge">&ge;</option>' +
	'<option value="le">&le;</option>' +
	'</select>';
}

/**
 * Adds a filter to the app_filters_id element.
 */
function add_filter() {
	if (num_filters == max_filters) {
		return false;
	}
	last_id++;
	num_filters++;
	var filter = document.createElement('span');
	filter.id = 'filter' + last_id;
	var innerHTML = '';
	if (num_filters == 1) {
		innerHTML = '<span style="visibility: hidden;">&#x2715;</span> ';
	}
	else {
		innerHTML = '<a href="#" onclick="return remove_filter(\'filter' +
			last_id + '\');">&#x2715;</a> ';
	}
	innerHTML += select_field('f[' + last_id + '][c]') + ' ' +
		select_relation('f[' + last_id + '][r]') +
		' <input name="f[' + last_id + '][v]" type="text" size="25"><br>';
	filter.innerHTML = innerHTML;
	document.getElementById(app_filters_id).appendChild(filter);
	return false;
}

/**
 * Removes the given filter from the app_filters_id element.
 */
function remove_filter(id) {
	num_filters--;
	var filter = document.getElementById(id);
	document.getElementById(app_filters_id).removeChild(filter);
	return false;
}

/**
 * Initializes the app_filters_id element.
 */
window.onload = function() {
	document.getElementById(app_filters_id).innerHTML =
		'Filter by up to ' + max_filters + ' criteria:<br>';
	var innerHTML = 'Sort by ' + select_field('s[0]') + ', then by ' +
		select_field('s[1]') + '<br>' +
		'<input id="m" name="m" type="checkbox" value="y"> ' +
		'<label for="m">Only show applications assigned to me for review</label> ' +
		'<input id="u" name="u" type="checkbox" value="y"> ' +
		'<label for="u">&hellip;that I have not reviewed yet</label><br>' +
		'<input type="button" onclick="add_filter();" value="Add filter"> ' +
		'<button name="a" type="submit" value="f">Filter</button>';
	document.getElementById(app_rest_id).innerHTML = innerHTML;
	add_filter();
}