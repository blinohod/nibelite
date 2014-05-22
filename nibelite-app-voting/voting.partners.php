<?php

/* by Anatoly Matyakh <protopartorg@gmail.com> 
 * SMS Votings - partners view
 */

include_once '../lib/php/webgui.inc.php'; 

$login = '';
$pass = '';
$logged = true;

function init_votingsms() {
	global $_PAGE, $login, $pass, $logged;

	read_templates(CONFIG.'/templates/partners.votingsms.html');

	$_PAGE = array(
		'page_title' => translate('votingsms_title'),
		'page_menu' => '',
		'page_main' => '',
		'page_time' => ''
	);

	$login = trim($_REQUEST['l']);
	$pass = trim($_REQUEST['p']);
	if (($login === '')||($pass === '')) {
		$_REQUEST['do'] = 'loginplease';
		$logged = false;
	}
	if ($logged && ($_REQUEST['id']+0 == 0)) {
		$data = db_get("select id from voting.votings where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' order by sn,descr limit 1");
		if ($data) {
			$_REQUEST['id'] = $data[0]['id'];
		}
	}
}

webgui_init();
webgui_run();

/* Active votings widget */

function widget_page_menu () {
	global $login,$pass,$logged;
	
	if ($logged) {
		$list = '';
		$data = db_get("select id,descr,sn from voting.votings where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' order by sn,descr");
		if ($data) {
			foreach ($data as $row) {
				$row['login'] = htmlspecialchars($login);
				$row['pass'] = htmlspecialchars($pass);
				$row['descr'] = htmlspecialchars($row['descr']);
				$list .= tpl('menu_voting',$row);
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
	return action_voting_results();
}

function action_loginplease () {
	return tpl('login');
}

function action_voting_results () {
	global $login,$pass,$logged;
	$id = $_REQUEST['id']+0;
	$xml = $_REQUEST['xml']+0;
	
	if ($logged && $id) {
		$tpl_list = $xml ? 'voting_list_xml' : 'voting_list';
		$tpl_item = $xml ? 'voting_list_item_xml' : 'voting_list_item';
	
		$data = db_get("select id,sn,descr from voting.votings where active and login='".db_escape($login)."' and passwd='".db_escape($pass)."' and id=$id limit 1");
		if ($data) {
			$sn = $data[0]['sn'];
			$descr = htmlspecialchars($data[0]['descr']);
			$total = 0;
			$votes = '';
			$data = db_get(
				"select keyword,descr,num_votes".
				" from voting.answers".
				" where voting_id=$id".
				" order by keyword"
			);
			if ($data) {
				foreach ($data as $row) {
					$total += $row['num_votes'];
				};

				foreach ($data as $row) {
					$row['descr'] = htmlspecialchars($row['descr']);
					$percentage = ($total > 0) ? (100 * $row['num_votes'] / $total ): 0;
					$row['share'] = sprintf ('%01.2f', $percentage);
					$votes .= tpl($tpl_item,$row);
				}
			}
			$out = tpl($tpl_list,array(
				'id'	=> $id,
				'login'	=> htmlspecialchars($login),
				'pass'	=> htmlspecialchars($pass),
				'sn'	=> $sn,
				'descr'	=> htmlspecialchars($descr),
				'votes'	=> $votes,
				'total'	=> $total
			));
			
			if ($xml) {
				header('Content-Type: text/xml');
				webgui_raw($out);
			} else {
				return $out;
			}
		}
	}
	
	return '';
}

