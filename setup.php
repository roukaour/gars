<?php

/**
 * This file sets up the MySQL tables needed for GARS to run. It depends on
 * config.php to access the database. It should be run once to install GARS,
 * and then removed from the public site directory.
 * GARS depends on PHP 5.2 and MySQL 5.0.
 */

require_once 'init.php';

use_class('Database');
use_class('Credentials_Manager');
use_class('Text');

# Track the success of every step of setup
$setup = true;

# Initialize database
$database = new Database();
if ($setup) $setup &=
$database->connect();
if ($setup) $setup &=
$database->create_tables();

# Populate users table
$creds = new Credentials_Manager();
$admin_salt = $creds->generate_salt();
$admin_hash = $creds->hash('password1', $admin_salt);
if ($setup) $setup &=
$database->query(sprintf("INSERT IGNORE INTO users
	(username, role, email, pass_hash, pass_salt) VALUES
	('admin', 'chair', 'admin@example.com', '%s', '%s')",
	mysql_real_escape_string($admin_hash),
	mysql_real_escape_string($admin_salt)
));

# Populate countries table
if ($setup) $setup &=
$database->query("INSERT IGNORE INTO countries
	(code, name) VALUES
	('AD', 'Andorra'),
	('AE', 'United Arab Emirates'),
	('AF', 'Afghanistan'),
	('AG', 'Antigua and Barbuda'),
	('AI', 'Anguilla'),
	('AL', 'Albania'),
	('AM', 'Armenia'),
	('AO', 'Angola'),
	('AQ', 'Antarctica'),
	('AR', 'Argentina'),
	('AS', 'American Samoa'),
	('AT', 'Austria'),
	('AU', 'Australia'),
	('AW', 'Aruba'),
	('AX', 'Aland Islands'),
	('AZ', 'Azerbaijan'),
	('BA', 'Bosnia and Herzegovina'),
	('BB', 'Barbados'),
	('BD', 'Bangladesh'),
	('BE', 'Belgium'),
	('BF', 'Burkina Faso'),
	('BG', 'Bulgaria'),
	('BH', 'Bahrain'),
	('BI', 'Burundi'),
	('BJ', 'Benin'),
	('BL', 'Saint Barthelemy'),
	('BM', 'Bermuda'),
	('BN', 'Brunei Darussalam'),
	('BO', 'Bolivia'),
	('BQ', 'Bonaire, Sint Eustatius and Saba'),
	('BR', 'Brazil'),
	('BS', 'Bahamas'),
	('BT', 'Bhutan'),
	('BV', 'Bouvet Island'),
	('BW', 'Botswana'),
	('BY', 'Belarus'),
	('BZ', 'Belize'),
	('CA', 'Canada'),
	('CC', 'Cocos Islands'),
	('CD', 'Democratic Republic of the Congo'),
	('CF', 'Central African Republic'),
	('CG', 'Congo'),
	('CH', 'Switzerland'),
	('CI', 'Ivory Coast'),
	('CK', 'Cook Islands'),
	('CL', 'Chile'),
	('CM', 'Cameroon'),
	('CN', 'China'),
	('CO', 'Colombia'),
	('CR', 'Costa Rica'),
	('CU', 'Cuba'),
	('CV', 'Cape Verde'),
	('CW', 'Curacao'),
	('CX', 'Christmas Island'),
	('CY', 'Cyprus'),
	('CZ', 'Czech Republic'),
	('DE', 'Germany'),
	('DJ', 'Djibouti'),
	('DK', 'Denmark'),
	('DM', 'Dominica'),
	('DO', 'Dominican Republic'),
	('DZ', 'Algeria'),
	('EC', 'Ecuador'),
	('EE', 'Estonia'),
	('EG', 'Egypt'),
	('EH', 'Western Sahara'),
	('ER', 'Eritrea'),
	('ES', 'Spain'),
	('ET', 'Ethiopia'),
	('FI', 'Finland'),
	('FJ', 'Fiji'),
	('FK', 'Falkland Islands'),
	('FM', 'Federated States of Micronesia'),
	('FO', 'Faroe Islands'),
	('FR', 'France'),
	('GA', 'Gabon'),
	('GB', 'United Kingdom'),
	('GD', 'Grenada'),
	('GE', 'Georgia'),
	('GF', 'French Guiana'),
	('GG', 'Guernsey'),
	('GH', 'Ghana'),
	('GI', 'Gibraltar'),
	('GL', 'Greenland'),
	('GM', 'Gambia'),
	('GN', 'Guinea'),
	('GP', 'Guadeloupe'),
	('GQ', 'Equatorial Guinea'),
	('GR', 'Greece'),
	('GS', 'South Georgia and the South Sandwich Islands'),
	('GT', 'Guatemala'),
	('GU', 'Guam'),
	('GW', 'Guinea-Bissau'),
	('GY', 'Guyana'),
	('HK', 'Hong Kong'),
	('HM', 'Heard Island and McDonald Islands'),
	('HN', 'Honduras'),
	('HR', 'Croatia'),
	('HT', 'Haiti'),
	('HU', 'Hungary'),
	('ID', 'Indonesia'),
	('IE', 'Ireland'),
	('IL', 'Israel'),
	('IM', 'Isle of Man'),
	('IN', 'India'),
	('IO', 'British Indian Ocean Territory'),
	('IQ', 'Iraq'),
	('IR', 'Iran'),
	('IS', 'Iceland'),
	('IT', 'Italy'),
	('JE', 'Jersey'),
	('JM', 'Jamaica'),
	('JO', 'Jordan'),
	('JP', 'Japan'),
	('KE', 'Kenya'),
	('KG', 'Kyrgyzstan'),
	('KH', 'Cambodia'),
	('KI', 'Kiribati'),
	('KM', 'Comoros'),
	('KN', 'Saint Kitts and Nevis'),
	('KP', 'North Korea'),
	('KR', 'South Korea'),
	('KW', 'Kuwait'),
	('KY', 'Cayman Islands'),
	('KZ', 'Kazakhstan'),
	('LA', 'Laos'),
	('LB', 'Lebanon'),
	('LC', 'Saint Lucia'),
	('LI', 'Liechtenstein'),
	('LK', 'Sri Lanka'),
	('LR', 'Liberia'),
	('LS', 'Lesotho'),
	('LT', 'Lithuania'),
	('LU', 'Luxembourg'),
	('LV', 'Latvia'),
	('LY', 'Libya'),
	('MA', 'Morocco'),
	('MC', 'Monaco'),
	('MD', 'Moldova'),
	('ME', 'Montenegro'),
	('MF', 'Saint Martin'),
	('MG', 'Madagascar'),
	('MH', 'Marshall Islands'),
	('MK', 'Macedonia'),
	('ML', 'Mali'),
	('MM', 'Myanmar'),
	('MN', 'Mongolia'),
	('MO', 'Macao'),
	('MP', 'Northern Mariana Islands'),
	('MQ', 'Martinique'),
	('MR', 'Mauritania'),
	('MS', 'Montserrat'),
	('MT', 'Malta'),
	('MU', 'Mauritius'),
	('MV', 'Maldives'),
	('MW', 'Malawi'),
	('MX', 'Mexico'),
	('MY', 'Malaysia'),
	('MZ', 'Mozambique'),
	('NA', 'Namibia'),
	('NC', 'New Caledonia'),
	('NE', 'Niger'),
	('NF', 'Norfolk Island'),
	('NG', 'Nigeria'),
	('NI', 'Nicaragua'),
	('NL', 'Netherlands'),
	('NO', 'Norway'),
	('NP', 'Nepal'),
	('NR', 'Nauru'),
	('NU', 'Niue'),
	('NZ', 'New Zealand'),
	('OM', 'Oman'),
	('PA', 'Panama'),
	('PE', 'Peru'),
	('PF', 'French Polynesia'),
	('PG', 'Papua New Guinea'),
	('PH', 'Philippines'),
	('PK', 'Pakistan'),
	('PL', 'Poland'),
	('PM', 'Saint Pierre and Miquelon'),
	('PN', 'Pitcairn'),
	('PR', 'Puerto Rico'),
	('PS', 'Palestine'),
	('PT', 'Portugal'),
	('PW', 'Palau'),
	('PY', 'Paraguay'),
	('QA', 'Qatar'),
	('RE', 'Reunion'),
	('RO', 'Romania'),
	('RS', 'Serbia'),
	('RU', 'Russia'),
	('RW', 'Rwanda'),
	('SA', 'Saudi Arabia'),
	('SB', 'Solomon Islands'),
	('SC', 'Seychelles'),
	('SD', 'Sudan'),
	('SE', 'Sweden'),
	('SG', 'Singapore'),
	('SH', 'Saint Helena, Ascension and Tristan da Cunha'),
	('SI', 'Slovenia'),
	('SJ', 'Svalbard and Jan Mayen'),
	('SK', 'Slovakia'),
	('SL', 'Sierra Leone'),
	('SM', 'San Marino'),
	('SN', 'Senegal'),
	('SO', 'Somalia'),
	('SR', 'Suriname'),
	('SS', 'South Sudan'),
	('ST', 'Sao Tome and Principe'),
	('SV', 'El Salvador'),
	('SX', 'Sint Maarten'),
	('SY', 'Syria'),
	('SZ', 'Swaziland'),
	('TC', 'Turks and Caicos Islands'),
	('TD', 'Chad'),
	('TF', 'French Southern Territories'),
	('TG', 'Togo'),
	('TH', 'Thailand'),
	('TJ', 'Tajikistan'),
	('TK', 'Tokelau'),
	('TL', 'Timor-Leste'),
	('TM', 'Turkmenistan'),
	('TN', 'Tunisia'),
	('TO', 'Tonga'),
	('TR', 'Turkey'),
	('TT', 'Trinidad and Tobago'),
	('TV', 'Tuvalu'),
	('TW', 'Taiwan'),
	('TZ', 'Tanzania'),
	('UA', 'Ukraine'),
	('UG', 'Uganda'),
	('UM', 'US Minor Outlying Islands'),
	('US', 'United States'),
	('UY', 'Uruguay'),
	('UZ', 'Uzbekistan'),
	('VA', 'Vatican City'),
	('VC', 'Saint Vincent and the Grenadines'),
	('VE', 'Venezuela'),
	('VG', 'Virgin Islands'),
	('VI', 'US Virgin Islands'),
	('VN', 'Vietnam'),
	('VU', 'Vanuatu'),
	('WF', 'Wallis and Futuna'),
	('WS', 'Samoa'),
	('YE', 'Yemen'),
	('YT', 'Mayotte'),
	('ZA', 'South Africa'),
	('ZM', 'Zambia'),
	('ZW', 'Zimbabwe')
");

# Populate research_areas table
if ($setup) $setup &=
$database->query("INSERT IGNORE INTO research_areas
	(name, abbr) VALUES
	('Artificial Intelligence', 'AI'),
	('Graphics and Visualization', 'Graphics'),
	('Networks', 'Networks'),
	('Programming languages/Verification', 'Verification'),
	('Security', 'Security'),
	('Systems', 'Systems'),
	('Theory', 'Theory'),
	('Vision/Natural Language Processing', 'NLP')
");

?>
<!DOCTYPE html>

<html lang="en" dir="ltr">

<head>
<meta charset="UTF-8">
<title>GARS Setup</title>
</head>

<body>
<?php if ($setup): ?>
<p>GARS setup is complete! You can now remove setup.php from the public site directory.</p>
<?php else: ?>
<p>An error occurred during setup.</p>
<p>MySQL error <?php echo Text::html_encode($database->error()); ?>.</p>
<?php endif; ?>
</body>

</html>