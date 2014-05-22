<?php

include_once 'init.inc.php'; 

include_once 'webgui/applications.webgui.php'; 
include_once 'webgui/datatypes.webgui.php'; 
#include_once 'webgui/phones.webgui.php'; 


#include_once 'translate.inc.php'; 
#include_once 'agents.inc.php'; 
#include_once 'testmode.inc.php'; 

$status = '';
$page_head = translate('config_head');
$page_main = '&nbsp;';

face_control('tech');

if( $_COOKIE['db_debug'] == 'on' ) $DEBUG_DB = true;
if( $_COOKIE['db_skip'] == 'on' ) $SKIP_DB = true;

read_templates(TEMPLATES.'design.config.'.$LANGUAGE.'.html');

$routing = new CMS('routing','core.routing',array(
	'priority' => '1000',
	'id'		=> '0',
	//'msg_type' => 'SMS_TEXT',
	'src_app_id' => 'id=>name from core.apps order by name',
	'body_regexp'	=> '',
	'src_addr_regexp'	=> '',
	'dst_addr_regexp'	=> '',
	'dst_app_id' => 'id=>name from core.apps order by name',
	'description' => ''
),1);


$apps = new CMSApplications();

if (!$page_main = $apps->handle()) {
	if (!$page_main = $routing->handle()) {
		$page_main = template($TPL['config_default'],array('script'=>$_SERVER['SCRIPT_NAME']));
	};
};

// Sending results to admin web browser.
echo template($TPL['page'],array(
	'page_title'	=> translate('config_title'),
	'page_head'		=> $page_head,
	'version'	  	=> VERSION,
	'page_menu'		=> template($TPL['config_menu'],array('script'=>$_SERVER['SCRIPT_NAME'])),
	'page_main'		=> $page_main,
	'page_status'	=> $status,
	'main_menu'   => make_main_menu(),
));

?>

