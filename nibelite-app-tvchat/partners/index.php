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

	global $_PAGE, $login, $pass, $logged;

	// Determine language
	if (isset ($_REQUEST['lang']) ) {
		$lang = $_REQUEST['lang'];
	} elseif (isset ($_SESSION['lang'])) {
		$lang = $_SESSION['lang'];
	} else {
		$lang = 'en'; // default to english (FIXME)
	};

	// TODO - real multi lang
	if ($lang != 'ru') { $lang = 'en'; };

	$_SESSION['lang'] = $lang; // set determined language

	# Read default templates
	read_templates(TEMPLATES.'partners.tvchat.'.$lang.'.html');

	# Overwrite customer specific templates
	if (file_exists(SYS . '/custom/partners.tvchat.html'))
		read_templates(SYS . '/custom/partners.tvchat.html');

	$_PAGE = array(
		'page_title' => translate('tvchat_title'),
		'page_menu' => '',
		'page_main' => '',
		'page_time' => '',
		'login_msg' => '',
	);

	if (isset($_SESSION['login']) && isset($_SESSION['password'])) {
		$login = $_SESSION['login'];
		$pass = $_SESSION['password'];
	} else {
		$login = trim($_REQUEST['l']);
		$pass = trim($_REQUEST['p']);
	}

	// If no login and password provided - definitely no login try
	if ( ($login == '') and ($pass == '') ) {
		$_REQUEST['do'] = 'loginplease';
		$logged = false;
	}

	if ($logged && ($_REQUEST['id']+0 == 0)) {

		$data = db_get("select id from tvchat.service where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' order by sn,name limit 1");

		if ($data) {
			$_REQUEST['id'] = $data[0]['id'];
			$_SESSION['login'] = $login;
			$_SESSION['password'] = $pass;
		} else {
			// Login error
			$_REQUEST['do'] = 'loginplease';
			$_PAGE['login_msg'] = tpl('login_error');
			$logged = false;
		};
	};

}

webgui_init();
webgui_run();

/* Active chats widget */

function widget_page_menu () {
	global $login,$pass,$logged;
	
	$m = $_REQUEST['m']+0; if (!$m) $m=200;

	if ($_REQUEST['do'] == 'logout') {
		return '';
	};

	if ($logged) {
		$list = '';
		$data = db_get("select s.id,s.name,s.sn,count(c.id) as num_msg from tvchat.service s left outer join tvchat.chat c on c.service_id=s.id where s.active and s.login='".db_escape($login)."' and s.passwd='".db_escape($pass)."' group by s.id, s.name, s.sn order by s.sn,s.name");
		if ($data) {
			foreach ($data as $row) {
				$row['login'] = htmlspecialchars($login);
				$row['pass'] = htmlspecialchars($pass);
				$row['name'] = htmlspecialchars($row['name']);
				$row['num_msg'] = $row['num_msg'];
				$row['limit'] = $m;
				$list .= tpl('menu_chat',$row);
			}
		}
		if ($list !== '') {
			return tpl('menu',array('list'=>$list));
		}
	}
	return '';
}

/* -----------------------------------------------------------------
 * ACTIONS 
 */

function action_default () {
	return action_chat();
}

function action_loginplease () {
	return tpl('login');
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
