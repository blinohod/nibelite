<?php 

include_once 'init.inc.php'; 
include_once 'webgui/messages.webgui.php'; 

face_control('support');

$status = '';
$page_head = translate('support_head');
$page_main = '&nbsp;';

if( $_COOKIE['db_debug'] == 'on' ) $DEBUG_DB = true;
if( $_COOKIE['db_skip'] == 'on' ) $SKIP_DB = true;

$requests = new CMSRequest();

if(!$_REQUEST['do']) $_REQUEST['do'] = 'request-list';

if(!($page_main = $requests->handle()))
  $page_main = template($TPL['support_default'],array('script'=>$_SERVER['SCRIPT_NAME']));

// Add status to page title
if ($status) {
	$page_head = $page_head.' : '.$status;
};

// Generate HTML page
echo template($TPL['page'],array(
	'page_title'	=> translate('support_title'),
	'page_head'		=> $page_head,
	'version'			=> VERSION,
	'page_status'	=> $status,
	'page_menu'		=> template($TPL['support_menu'],array('script'=>$_SERVER['SCRIPT_NAME'])),
	'page_main'		=> $page_main,
	'main_menu'   => make_main_menu(),
));


?>
