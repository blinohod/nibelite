<?php
/*
 * $Id: nibelite.config.php 21 2007-09-19 15:50:00Z misha $
 *
 * Main configuration file for Nibelung D4 Lite.
 *
 * NOTE: Previous version of Nibelite configuration was unclear enough and is rewritten now.
 */

// Debugging options. Use carefully on production systems to avoid delivering unexpected information to subscribers ;-)
$DEBUG = false; // Set debugging flag for all platform applications.
$DEBUG_DB = false; // Set debugging flag for DBMS queries.

// $NO_USE_PGSQL = false; // No DBMS connection if set to true.

$CONFIG = Array(

	// PostgreSQL database connection settings
	'db_host'	=> '', // Host name or IP address of PostgreSQL DBMS. Local socket if empty string.
	'db_port' => '', // PostgreSQL port (if not default 5432).
	'db_name' => 'nibelite', // PostgreSQL default database name.
	'db_user' => 'nibelite', // PostgreSQL connection user name.
	'db_pass' => 'nibelite', // PostgreSQL password.

	// Localization settings.
	//'language' => 'ru',
	'language' => 'en',

	'wap' => Array (
		'default_markup' => 'wml',
		),

	);


$LANGUAGE = 'ru';


?>
