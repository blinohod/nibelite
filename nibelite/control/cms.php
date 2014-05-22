<?php
/* $Id: index.php 26 2007-09-19 16:58:45Z misha $
 *
 * Nibelite SDS content management system (CMS).
 *
 * Functionality:
 * - Managing catalog.
 * - Managing content items.
 */

include_once 'init.inc.php';

include_once 'webgui/simple.webgui.php';
include_once 'webgui/content.webgui.php'; 
include_once 'webgui/catalog.webgui.php'; 

face_control('content');

$status = '';
$page_head = translate('content_head');
$page_main = '&nbsp;';

if( $_COOKIE['db_debug'] == 'on' ) $DEBUG_DB = true;
if( $_COOKIE['db_skip'] == 'on' ) $SKIP_DB = true;

$content = new CMSContent();
$catalog = new CMSCatalog();

if(!isset($_REQUEST['do'])) $_REQUEST['do'] = 'content-list';

if(!$page_main = $content->handle())
if(!$page_main = $catalog->handle())
	$page_main = tpl('content_default',array('script'=>$_SERVER['SCRIPT_NAME']));

// if($status) $page_head = $page_head.' : '.$status;
echo tpl('page',array(
	'page_title'  => translate('content_title'),
	'page_head'   => $page_head,
	'page_status' => $status,
	'page_menu'   => tpl('content_menu',array('script'=>$_SERVER['SCRIPT_NAME'])),
	'version'     => VERSION,
	'page_main'   => $page_main,
	'main_menu'   => make_main_menu(),
));


?>

