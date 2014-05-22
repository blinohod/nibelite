<?php 

require_once 'init.inc.php'; 

require_once 'webgui/simple.webgui.php';
require_once 'webgui/auth.webgui.php';

$status = '';
$page_head = translate('auth_head');
$page_main = '&nbsp;';
$page_menu = '&nbsp;';

$uid = authenticate();

$output_open = '&nbsp';

$auth = new CMSAuth();

if (!$page_main = $auth->handle()) {
	if ($uid) {
		$page_main = template($TPL['auth_welcome'],array('script'=>$_SERVER['SCRIPT_NAME']));
		$page_menu = template($TPL['auth_menu'],array('script'=>$_SERVER['SCRIPT_NAME']));
	} else {
		$page_main = template($TPL['auth_login_form'],array('script'=>$_SERVER['SCRIPT_NAME']));
	}
};

if($output_open){
  if($status) $page_head = $page_head.' : '.$status;
  echo template($TPL['page'],array(
    'page_title'  => translate('auth_title'),
    'page_head'   => $page_head,
    'page_menu'   => $page_menu,
    'version'     => VERSION,
    'page_main'   => $page_main,
	  'page_status' => $status,
		'main_menu'   => make_main_menu(),
  ));
}


?>
