<?php
session_start();
$_SESSION['last_access'] = time();

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
	global $_PAGE, $login, $pass, $logged, $LANGUAGE;

	# Read default templates
	read_templates('chat.html');

	# Overwrite customer specific templates
	if (file_exists(SYS . '/custom/partners.tvchat.html'))
		read_templates(SYS . '/custom/partners.tvchat.html');

	$_PAGE = array(
		'page_main' => '',
	);

	if (isset($_SESSION['login']) && isset($_SESSION['password'])) {
		$login = $_SESSION['login'];
		$pass = $_SESSION['password'];
	} else {
		$login = trim($_REQUEST['login']);
		$pass = trim($_REQUEST['pass']);
	}

	// If no login and password provided - definitely no login try
	if ( ($login == '') and ($pass == '') ) {
		$logged = false;
	}

	if ($logged && ($_REQUEST['id']+0 == 0)) {

		$data = db_get("select id from tvchat.service where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' order by sn,name limit 1");

		if ($data) {
			$_REQUEST['id'] = $data[0]['id'];
			$_SESSION['login'] = $login;
			$_SESSION['password'] = $pass;
		} else {
			$logged = false;
		};
	};

}

webgui_init();
webgui_run();

/* -----------------------------------------------------------------
 * ACTIONS 
 */

function action_default () {
	return '';
}

function action_check () {
	global $login,$logged;
	if ($logged) {
		webgui_raw($login);
	} else {
		webgui_raw('-');
	}
	return '';
}

function action_menu () {
	global $login,$pass,$logged;
	if ($logged) {
		$list = '';
		$data = db_get("select id,name,sn from tvchat.service where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' order by sn,name");
		if ($data) {
			foreach ($data as $row) {
				$row['mtoday'] = 0;
				$tdata = db_get("select count(*) from tvchat.chat where service_id=".$data['id']." and received >= date(now())");
				if ($tdata) $row['mtoday'] = $tdata['count'];
				$row['mmonth'] = 0;
				$tdata = db_get("select count(*) from tvchat.chat where service_id=".$data['id']." and received >= date(now()-interval '30 days')");
				if ($tdata) $row['mmonth'] = $tdata['count'];
				$row['name'] = htmlspecialchars($row['name']);
				$list .= tpl('menu_chat',$row);
			}
		}
		webgui_raw(tpl('menu',array('list'=>$list)));
	}
	return '';
}


function action_login () {
	return action_check;
}

function action_logout () {
	unset($_SESSION['login']);
	unset($_SESSION['password']);
	return action_loginplease();
}

function action_chat () {

	global $login,$pass,$logged;

	$id = $_REQUEST['id']+0;
	$xml = $_REQUEST['xml']+0;
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
			//$data = db_get("select count(*) as cnt from tvchat.chat where sn='$sn' and status='APPROVED'");
			//$data = db_get("select count(*) as cnt from tvchat.chat where sn='$sn'");
			if ($data) $total = $data[0]['cnt'];
			
			if ($total and !$xml) {
			
				$chat = '';
				
				if ($appr) {
					$sql_chat = "select id,sn,status,msisdn,body,to_char(received,'YYYY-MM-DD HH24:MI:SS') as time_full,to_char(received,'HH24:MI') as time_short".
					" from tvchat.chat".
					" where service_id=$id".
					" and status='APPROVED'".
					" order by id desc".
					" limit $m offset $s";
				} else {
					$sql_chat = "select id,sn,status,msisdn,body,to_char(received,'YYYY-MM-DD HH24:MI:SS') as time_full,to_char(received,'HH24:MI') as time_short".
					" from tvchat.chat".
					" where service_id=$id".
					" order by id desc".
					" limit $m offset $s";
				}
				$data = db_get($sql_chat);
				if ($data) {
					foreach ($data as $row) {
						$row['body'] = htmlspecialchars($row['body']);
						$row['class'] = 'chat-'.strtolower($row['status']);
						$chat .= tpl($tpl_item,$row);
					}
				}
				
				$chat_nav = '';
				if (!$xml) {
					$links = array();
					if ($s >= $m) 
						$links[] = tpl('chat_prev_link',array(
							'id'	=> $id,
							'login'	=> htmlspecialchars($login),
							'pass'	=> htmlspecialchars($pass),
							'start' => $s - $m,
							'limit' => $m,
						));
					if ($s+$m < $total)
						$links[] = tpl('chat_next_link',array(
							'id'	=> $id,
							'login'	=> htmlspecialchars($login),
							'pass'	=> htmlspecialchars($pass),
							'start' => $s + $m,
							'limit' => $m,
						));
					$chat_nav = tpl('chat_nav',array(
						'start'		=> $s+1,
						'end'		=> $total < $s+$m ? $total : $s+$m,
						'total'		=> $total,
						'links'		=> implode('&nbsp;|&nbsp;',$links),
						'limit' => $m,
					));
				}
				
				$out = tpl($tpl_list,array(
					'id'	=> $id,
					'login'	=> htmlspecialchars($login),
					'pass'	=> htmlspecialchars($pass),
					'sn'	=> $sn,
					'name'	=> htmlspecialchars($name),
					'messages'	=> $chat,
					'chat_nav'	=> $chat_nav,
					'num'	=> $m
				));
			} else {
				$out = tpl('chat_empty',array(
					'sn'	=> $sn,
					'name'	=> htmlspecialchars($name),
				));
			}
			
			if ($xml) {
				header('Content-Type: application/rss+xml');
				webgui_raw($out);
			} else {
				return $out;
			}
		}
	}
	
	return '';
}
