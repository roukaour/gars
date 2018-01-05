<?php

/**
 * This file contains configuration data for GARS. It is used by init.php.
 */

# The base URL for all of GARS' files; protocol-relative to allow HTTPS switch
define('BASE_URL', '//localhost/~remy/gars/');

# The character set used to store and output strings
define('CHARSET', 'UTF-8');

# Database information
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'password1');
define('DB_NAME', 'gars');
define('DB_CHARSET', 'utf8');

# Password hashing
define('HASH_TYPE', 'whirlpool');
define('SALT_BITS', 512);

# Mapping files (in the maps directory)
define('AY_MAP', 'AY-map.txt');
define('OTS_MAP', 'OTS-map.txt');
define('COUNTRY_MAP', 'country-map.txt');
define('INSTITUTION_MAP', 'institution-map.txt');

# Applications table
define('APPS_PER_PAGE', 10);

# Maximum pending reviews/decisions on home page
define('MAX_PENDING_PREVIEW', 10);

# Importance of areas of expertise when auto-assigning reviews
define('COUNTRY_IMPORTANCE', 2.5);
define('RESEARCH_AREA_IMPORTANCE', 3.0);

?>