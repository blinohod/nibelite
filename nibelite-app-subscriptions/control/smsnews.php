<?php
/*
 * SMS News Admin Subsystem
 */

// Find installation root and initialize all this stuff.
if (getenv('NIBELITE_HOME')) {
  include_once(getenv('NIBELITE_HOME')."/etc/nibelite.init.php");
} else {
  include_once("/opt/nibelite/etc/nibelite.init.php");
}

include_once SYS . '/lib/php/webgui.inc.php';

include_once 'webgui/smsnews_topics.web2.php';
include_once 'webgui/smsnews_catsub.web2.php';
include_once 'webgui/smsnews_subscribers.web2.php';
include_once 'webgui/smsnews_messages.web2.php';
include_once 'webgui/smsnews_stat.web2.php';
include_once 'webgui/smsnews_log.web2.php';
include_once 'webgui/smsnews_req.web2.php';

face_control('smsnews');

function init_smsnews() {
	global $_PAGE, $LANGUAGE;

	read_templates(TEMPLATES.'design.smsnews.'. $LANGUAGE.'.html');

	$_PAGE = array(
		'page_status' => '',
		'page_title' => translate('smsnews_title'),
		'page_head' => translate('smsnews_head'),
		'page_menu' => tpl('smsnews_menu'),
		'page_main' => '',
		'page_time' => '',
		'main_menu' => make_main_menu(),
	);

}

function action_default() {
	return tpl('smsnews_default');
}

webgui_init();
webgui_run();
?>

