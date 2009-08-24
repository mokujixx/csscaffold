<?php

/**
 * Absolute url path to your css directory. eg. /themes/css/
 */
$css_dir = "../";

/**
 * Leave this unless you would like to set something other 
 * than the default system/cache/ folder.  Use a full server 
 * path with trailing slash.
 * 
 * Default is the cache folder inside the system directory
 */
$cache_dir = "cache";

/**
 * Make sure the we're using PHP 5.2 or newer
 */
version_compare(PHP_VERSION, '5.2', '<') and exit('CSScaffold requires PHP 5.2 or newer.');

/**
 * Set the error reporting level. Unless you have a special need, E_ALL is a
 * good level for error reporting.
 */
error_reporting(E_ALL & ~E_STRICT);

/**
 * Setting it to false will remove all errors
 */
ini_set('display_errors', TRUE);

/**
 * Don't touch anything below here.
 */
$path = pathinfo(__FILE__);

# This file
define('SCAFFOLD', $path['basename']);

# If this is a symlink, change to the real file
is_link(SCAFFOLD) and chdir(dirname(realpath(__FILE__)));

# Set the constants
define('DOCROOT', 	$_SERVER['DOCUMENT_ROOT']);
define('SYSPATH', 	$path['dirname']);
define('CACHEPATH', realpath($cache_dir));
define('CSSPATH',	realpath($css_dir));
define('CSSURL', 	str_replace(DOCROOT, '/', CSSPATH));
define('SYSURL', 	str_replace(DOCROOT, '/', SYSPATH));

# Clean up
unset($cache_dir, $css_dir, $path); 

if(file_exists(SYSPATH . '/install.php'))
{
	require SYSPATH . '/install.php';
}
else
{
	require SYSPATH . '/core/Bootstrap.php';
}
