<?php 

require_once 'init.inc.php'; 

require_once 'webgui/simple.webgui.php';
require_once 'webgui/reports.webgui.php'; 

$status = '';
$page_head = translate('billing_head');
$page_main = '&nbsp;';
$output_open = true;

face_control('billing');

if( $_COOKIE['db_debug'] == 'on' ) $DEBUG_DB = true;
if( $_COOKIE['db_skip'] == 'on' ) $SKIP_DB = true;

read_templates(TEMPLATES.'design.billing.'.$LANGUAGE.'.html');

$plans = new CMS('plan','core.plan',array(
	'id' => '0',
	'name' => '',
	'provider' => '',
	'title' => '',
	'taxes' => '0.20',
	'cost' => '0',
	'income' => '0'
));

$reports = new BillingReport();

if(!$page_main = $plans->handle())
	if(!$page_main = $reports->handle())
		$page_main = tpl('billing_default',
			array('script'=>$_SERVER['SCRIPT_NAME']));

if($output_open){
  if($status) $page_head = $page_head.' : '.$status;
  echo template($TPL['page'],array(
    'page_title'  => translate('billing_title'),
    'page_head'   => $page_head,
    'page_menu'   => template($TPL['billing_menu'],array('script'=>$_SERVER['SCRIPT_NAME'])),
    'version'     => VERSION,
    'page_main'   => $page_main,
	  'page_status' => $status,
		'main_menu'   => make_main_menu(),
  ));
}


?>
