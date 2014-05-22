<?php

/* by Anatoly Matyakh <protopartorg@gmail.com> 
 * SMS TV Chat - partners view
 */

// Find installation root and initialize all this stuff.
if (getenv('NIBELITE_HOME')) {
	include_once(getenv('NIBELITE_HOME')."/etc/nibelite.init.php");
} else {
	include_once("/opt/nibelite/etc/nibelite.init.php");
}

include_once SYS . '/lib/php/webgui.inc.php'; 

$login = '';
$pass = '';
$logged = true;

function init_tvchat() {

	global $_PAGE, $login, $pass, $logged;

	# Read default templates
	read_templates(TEMPLATES.'partners.tvchat.xml.html');
	
	$_PAGE = array(
		'page_title' => translate('tvchat_title'),
		'page_menu' => '',
		'page_main' => '',
		'page_time' => '',
		'login_msg' => '',
	);

	$login = trim($_REQUEST['l']);
	$pass = trim($_REQUEST['p']);

	// If no login and password provided - definitely no login try
	if ( ($login == '') and ($pass == '') ) {
		$_REQUEST['do'] = 'loginplease';
		$logged = false;
	}

}

webgui_init();
webgui_run();

/* Active chats widget */


function action_loginplease () {
	return tpl('login');
}

function action_chat () {

	global $login,$pass,$logged;

	$id = $_REQUEST['id']+0;
	$xml = 1;
	$m = $_REQUEST['m']+0; if (!$m) $m=200;
	$s = $_REQUEST['s']+0;
	$appr = $_REQUEST['appr']+0;
	
	if ($logged && $id) {
		$tpl_list = $xml ? 'chat_list_xml' : 'chat_list';
		$tpl_item = $xml ? 'chat_list_item_xml' : 'chat_list_item';
	
		$data = db_get("select id,sn,name from tvchat.service where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' and id=$id limit 1");
		if ($data) {

			$sn = $data[0]['sn'];
			$name = htmlspecialchars($data[0]['name']);
			$total = 0;
			
			$data = db_get("select count(*) as cnt from tvchat.chat where service_id=$id");
			if ($data) {
				$total = $data[0]['cnt'];
			};
			
			if ($appr) {
        $sql_chat = "select id,status,msisdn,body,to_char(received,'YYYY-MM-DD HH24:MI:SS') as time_full,to_char(received,'HH24:MI') as time_short".
        " from tvchat.chat".
        " where service_id=$id".
        " and status='APPROVED'".
        " order by id desc".
        " limit $m offset $s";
      } else {
        $sql_chat = "select id,status,msisdn,body,to_char(received,'YYYY-MM-DD HH24:MI:SS') as time_full,to_char(received,'HH24:MI') as time_short".
        " from tvchat.chat".
        " where service_id=$id".
        " order by id desc".
        " limit $m offset $s";
      }
      $data = db_get($sql_chat);
      if ($data) {
        foreach ($data as $row) {
          $row['body'] = htmlspecialchars($row['body']);
          $chat .= tpl($tpl_item,$row);
        }
      }

      $chat_nav = '';

      $out = tpl($tpl_list,array(
        'id'  => $id,
        'login' => htmlspecialchars($login),
        'pass'  => htmlspecialchars($pass),
        'sn'  => $sn,
        'name'  => htmlspecialchars($name),
        'messages'  => $chat,
        'chat_nav'  => $chat_nav,
        'num' => $m
      ));

			
			header('Content-Type: application/rss+xml');
			webgui_raw($out);

		}

	}
	
	return '';
}
