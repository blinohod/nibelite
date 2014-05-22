<?php
/*
 * $Id: nibelite.init.php 26 2007-09-19 16:58:45Z misha $
 *
 * Initialization section for all Nibelite PHP applications.
 *
 * Usage:
 * 
 * Every PHP script should read NIBELITE_HOME environment variable
 * and include file 'config/nibelite.init.php' under this.
 * 
 * If no NIBELITE_HOME exists use '/opt/nibelite' as default value.
 *
 * Functionality:
 *
 * - Set include path for PHP libraries access.
 * - Include system configuration file.
 * - Set system wide variables and named constants.
 * - Initialize DBMS connection.
 */


define('VERSION','Nibelite v 5.0'); // Platform version

// ******************************************************************************************* 
// System path initialization and PHP include_path update.

$SYS = '/opt/nibelite'; // Default Nibelite installation path.

// Set PHP include path
if (getenv('NIBELITE_HOME')) {
	set_include_path(get_include_path() . PATH_SEPARATOR . getenv('NIBELITE_HOME').'/lib/php');
	$SYS = getenv('NIBELITE_HOME');
} else {
	set_include_path(get_include_path() . PATH_SEPARATOR . '/opt/nibelite/lib/php');
};


// ******************************************************************************************* 
// Set named constants values with files and directories paths.

define('SYS',$SYS); // Nibelite home directory.
define('TEMPLATES',$SYS.'/share/templates-old/'); // Nibelite home directory.
define('CONFIG',$SYS.'/etc'); // Configuration direcory (main config, templates, reports, etc).
define('CMS',$SYS.'/control'); // Management web console.
define('EXPORT',$SYS.'/export'); // FIXME: some old stuff
define('IMPORT',$SYS.'/import'); // FIXME: some old stuff
define('STORAGE_PATH',$SYS.'/var/content/'); // Content storage
define('PREVIEW_PATH',$SYS.'/wap/preview/'); // Preview for WAP/WWW sites
define('PREVIEW_WEB_PATH','/preview/'); // FIXME: what difference between PREVIEW_PATH ?!

define('AUTH_GROUP_FILE',CONFIG.'/groups'); // Management web console group access rights.
define('LOG_FILE',$SYS.'/var/log/errors.log'); // FIXME: only one place where used.


// ******************************************************************************************* 
// Initialize globally used variables.

// Language constants
$ALLOWED_LANG = array('ru','en');
$DEFAULT_LANGUAGE = 'en';

// Date constants
$YEARS = array(
	'2010'=>'2010',
	'2011'=>'2011',
	'2012'=>'2012',
	'2013'=>'2013',
	'2014'=>'2014',
	'2015'=>'2015',
	'2016'=>'2016',
	'2017'=>'2017',
	'2018'=>'2018',
	'2019'=>'2019',
	'2020'=>'2020'
);

$MONTHS = array(
	'01'=>'Jan',
	'02'=>'Feb',
	'03'=>'Mar',
	'04'=>'Apr',
	'05'=>'May',
	'06'=>'Jun',
	'07'=>'Jul',
	'08'=>'Aug',
	'09'=>'Sep',
	'10'=>'Oct',
	'11'=>'Nov',
	'12'=>'Dec'
);

$DAYS = array(
	'01'=>1,
	'02'=>2,
	'03'=>3,
	'04'=>4,
	'05'=>5,
	'06'=>6,
	'07'=>7,
	'08'=>8,
	'09'=>9,
	'10'=>10,
	'11'=>11,
	'12'=>12,
	'13'=>13,
	'14'=>14,
	'15'=>15,
	'16'=>16,
	'17'=>17,
	'18'=>18,
	'19'=>19,
	'20'=>20,
	'21'=>21,
	'22'=>22,
	'23'=>23,
	'24'=>24,
	'25'=>25,
	'26'=>26,
	'27'=>27,
	'28'=>28,
	'29'=>29,
	'30'=>30,
	'31'=>31
);

// PostgreSQL results
$PG = array(
	'EMPTY QUERY',
	'OK',
	'TUPLES OK',
	'COPY TO',
	'COPY FROM',
	'BAD RESPONSE',
	'NONFATAL ERROR',
	'FATAL ERROR'
);

// Startups and globals
$LANG = array();
$TPL = array();
$CC = array();
$GROUPS = array();


// ******************************************************************************************* 
// Initialize Nibelite subsystems.

// Read configuration file before.
include_once(CONFIG.'/nibelite.config.php'); // read configuration file

// Connect to PostgreSQL
include_once('pgsql.inc.php');
db_connect();

// Initialize constants and common functions.
include_once('common.inc.php');
include_once('core.inc.php');

// Initialize language and translations
init_language($LANGUAGE);


?>
