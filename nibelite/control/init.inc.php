<?php
/*
 * $Id: init.inc.php 21 2007-09-19 15:50:00Z misha $
 *
 * Initialization section for all administrative web-tools.
 */

// Find installation root and initialize all this stuff.
if (getenv('NIBELITE_HOME')) { 
	include_once(getenv('NIBELITE_HOME')."/etc/nibelite.init.php");
} else {
	include_once("/opt/nibelite/etc/nibelite.init.php");
}

?>
