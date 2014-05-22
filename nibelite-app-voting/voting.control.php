<?php

/* Total remake by Anatoly Matyakh <protopartorg@gmail.com> 
 * All business logic is implemented below - no unique includes 
 */

include_once '../lib/php/webgui.inc.php'; 

function init_votingsms() {
	global $_PAGE;

	face_control('votingsms');
	read_templates(CONFIG.'/templates/design.votingsms.html');

	$_PAGE = array(
		'page_status' => '',
		'page_title' => translate('votingsms_title'),
		'page_head' => translate('votingsms_head'),
		'page_menu' => tpl('votingsms_menu'),
		'page_main' => '',
		'page_time' => ''
	);

}

/* function action_default() {
	return tpl('votingsms_default');
} */

webgui_init();
webgui_run();

/* -----------------------------------------------------------------
 * ADVERTISING MANAGEMENT ACTIONS 
 */

function action_default () {
	return tpl('vs_manage');
}

function action_votingsms_show () {
	$raw = '';
	
	$page = $_REQUEST['page']+0;
	$since = date('d.m.Y');
	$till = date('d.m.Y',time()+3600*24*10);
	
	$def_ok = '';
	$def_err = '';
	$def_fail = '';
	$def_help = '';
	$data = db_get("select answer_id,reply from voting.replies where voting_id=0");
	if ($data) 
		foreach ($data as $row) 
			if ($row['answer_id']==0) 
				$def_ok = htmlspecialchars($row['reply']);
			elseif ($row['answer_id']==-1) 
				$def_err = htmlspecialchars($row['reply']);
			elseif ($row['answer_id']==-2) 
				$def_fail = htmlspecialchars($row['reply']);
			elseif ($row['answer_id']==-10) 
				$def_help = htmlspecialchars($row['reply']);
	
	$data = db_get("select count(*) as cnt from voting.votings");
	$raw = 'database error';
	if ($data) {
		$total = $data[0]['cnt'];
		if ($total) {
			$limit = 20;
			$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
			$start = $offset + 1;
			$end = $offset + $limit; if ($end > $total) $end = $total; // UI
		
			$back = $page>0 ? tpl('vs_back') : '';
			$next = $end<$total ? tpl('vs_more') : '';
		
			$data = db_get(
				"select".
					" v.id, v.active, v.descr, v.since::date, v.till::date, v.login, v.passwd, v.multivote, v.sn".
				" from voting.votings v".
				" order by active desc, till desc".
				" limit $limit offset $offset"
			);
			
			if ($data) {
				$list = '';
				
				foreach ($data as $row) {
					// id, active, descr, since, till, login, passwd, multivote
					$voting_id = $row['id'];
					$row['descr'] = htmlspecialchars($row['descr']);
					$row['login'] = htmlspecialchars($row['login']);
					$row['passwd'] = htmlspecialchars($row['passwd']);
					$row['actcheck'] = $row['active']=='t' ? 'checked="1"' : '';
					$row['active'] = tpl( 
						$row['active']=='t' ? 'vs_active' : 'vs_inactive',
						array('id'=>$voting_id)
					);
					$row['multicheck'] = $row['multivote']=='t' ? 'checked="1"' : '';
					$row['multivote'] = $row['multivote']=='t' ? 'Multi-vote' : 'Single vote';
					$row['answers'] = '';
					$row['votes'] = 0;
					
					$row['def_ok'] = $def_ok;
					$row['def_err'] = $def_err;
					$row['def_fail'] = $def_fail;
					$row['def_help'] = $def_help;
					$row['voting_ok'] = '';
					$row['voting_err'] = '';
					$row['voting_fail'] = '';
					$row['voting_help'] = '';
					$datar = db_get("select answer_id,reply from voting.replies where voting_id=$voting_id");
					if ($datar) 
						foreach ($datar as $rec) 
							if ($rec['answer_id']==0) 
								$row['voting_ok'] = htmlspecialchars($rec['reply']);
							elseif ($rec['answer_id']==-1) 
								$row['voting_err'] = htmlspecialchars($rec['reply']);
							elseif ($rec['answer_id']==-2) 
								$row['voting_fail'] = htmlspecialchars($rec['reply']);
							elseif ($rec['answer_id']==-10) 
								$row['voting_help'] = htmlspecialchars($rec['reply']);
					
					$answers = db_get(
						"select id,voting_id,keyword,descr,num_votes".
						" from voting.answers".
						" where voting_id=$voting_id".
						" order by keyword"
					);
					if ($answers) {
						foreach ($answers as $answer) {
							$answer['descr'] = htmlspecialchars($answer['descr']);
							$answer['keyword'] = htmlspecialchars($answer['keyword']);
							$answer['reply_def'] = ($row['voting_ok'] === '') ? $def_ok : $row['voting_ok'];
							$answer['reply_ok'] = '';
							$dataa = db_get("select reply from voting.replies where voting_id=$voting_id and answer_id=".$answer['id']);
							if($dataa) $answer['reply_ok'] = $dataa[0]['reply'];
							$row['answers'] .= tpl('vs_answer',$answer);
							$row['votes'] += $answer['num_votes'];
						}
					}
					
					$list .= tpl('vs_item', array(
						'id' => $voting_id,
						'voting' => tpl('vs_voting',$row)
					));
				}
				
				$raw = tpl('vs_list',array(
					'total'		=> $total,
					'start'		=> $start,
					'end'		=> $end,
					'back'		=> $back,
					'more'		=> $next,
					'list'		=> $list,
					'since'		=> $since,
					'till'		=> $till,
					'def_ok'	=> $def_ok,
					'def_err'	=> $def_err,
					'def_fail'	=> $def_fail,
					'def_help'	=> $def_help
				));
			}
		} else {
			$raw = tpl('vs_list',array(
				'total'		=> 0,
				'start'		=> 0,
				'end'		=> 0,
				'back'		=> '',
				'more'		=> '',
				'list'		=> tpl('vs_empty'),
				'since'		=> $since,
				'till'		=> $till,
				'def_ok'	=> $def_ok,
				'def_err'	=> $def_err,
				'def_fail'	=> $def_fail,
				'def_help'	=> $def_help
			));
		}
	}
	
	webgui_raw($raw);
}

function action_votingsms_replydefupdate () {
	$raw = 'DONE';
	$def_ok = db_escape(trim($_REQUEST['def_ok'].''));
	$def_err = db_escape(trim($_REQUEST['def_err'].''));
	$def_fail = db_escape(trim($_REQUEST['def_fail'].''));
	
	db("delete from voting.replies where voting_id=0 and answer_id != -10");
	db("insert into voting.replies (voting_id,answer_id,reply) values (0,0,'$def_ok')");
	db("insert into voting.replies (voting_id,answer_id,reply) values (0,-1,'$def_err')");
	db("insert into voting.replies (voting_id,answer_id,reply) values (0,-2,'$def_fail')");
	
	webgui_raw($raw);
}


function action_votingsms_vdata () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	
	if ($id) {
		$data = db_get(
			"select".
				" id, active, descr, since::date, till::date, login, passwd, multivote, sn".
			" from voting.votings".
			" where id=$id"
		);
		$raw = 'Database Error';
		if ($data) {
			$row = $data[0];
			// id, active, descr, since, till, login, passwd, multivote
			$voting_id = $row['id'];
			$row['descr'] = htmlspecialchars($row['descr']);
			$row['login'] = htmlspecialchars($row['login']);
			$row['passwd'] = htmlspecialchars($row['passwd']);
			$row['actcheck'] = $row['active']=='t' ? 'checked="1"' : '';
			$row['active'] = tpl( 
				$row['active']=='t' ? 'vs_active' : 'vs_inactive',
				array('id'=>$voting_id)
			);
			$row['multicheck'] = $row['multivote']=='t' ? 'checked="1"' : '';
			$row['multivote'] = $row['multivote']=='t' ? 'Multi-vote' : 'Single vote';
			$row['answers'] = '';
			$row['votes'] = 0;
					
			$row['def_ok'] = '';
			$row['def_err'] = '';
			$row['def_fail'] = '';
			$row['def_help'] = '';
			$row['voting_ok'] = '';
			$row['voting_err'] = '';
			$row['voting_fail'] = '';
			$row['voting_help'] = '';
			$data = db_get("select answer_id,reply from voting.replies where voting_id=$voting_id");
			if ($data) 
				foreach ($data as $rec) 
					if ($rec['answer_id']==0) 
						$row['voting_ok'] = htmlspecialchars($rec['reply']);
					elseif ($rec['answer_id']==-1) 
						$row['voting_err'] = htmlspecialchars($rec['reply']);
					elseif ($rec['answer_id']==-2) 
						$row['voting_fail'] = htmlspecialchars($rec['reply']);
					elseif ($rec['answer_id']==-10) 
						$row['voting_help'] = htmlspecialchars($rec['reply']);
			$data = db_get("select answer_id,reply from voting.replies where voting_id=0");
			if ($data) 
				foreach ($data as $rec) 
					if ($rec['answer_id']==0) 
						$row['def_ok'] = htmlspecialchars($rec['reply']);
					elseif ($rec['answer_id']==-1) 
						$row['def_err'] = htmlspecialchars($rec['reply']);
					elseif ($rec['answer_id']==-2) 
						$row['def_fail'] = htmlspecialchars($rec['reply']);
					elseif ($rec['answer_id']==-10) 
						$row['def_help'] = htmlspecialchars($rec['reply']);
					
			$answers = db_get(
				"select id,voting_id,keyword,descr,num_votes".
				" from voting.answers".
				" where voting_id=$voting_id".
				" order by keyword"
			);
			if ($answers) {
				foreach ($answers as $answer) {
					$answer['descr'] = htmlspecialchars($answer['descr']);
					$answer['keyword'] = htmlspecialchars($answer['keyword']);
					$answer['reply_def'] = ($row['voting_ok'] === '') ? $row['def_ok'] : $row['voting_ok'];
					$answer['reply_ok'] = '';
					$dataa = db_get("select reply from voting.replies where voting_id=$voting_id and answer_id=".$answer['id']);
					if($dataa) $answer['reply_ok'] = $dataa[0]['reply'];
					$row['answers'] .= tpl('vs_answer',$answer);
					$row['votes'] += $answer['num_votes'];
				}
			}
					
			$raw = tpl('vs_voting',$row);
		}
	}
	
	webgui_raw($raw);
}


function action_votingsms_vinsert () {
	$raw = 'DONE';
	
	$descr = db_escape(trim($_REQUEST['descr'].''));
	$since = db_escape(trim($_REQUEST['since'].''));
	$till = db_escape(trim($_REQUEST['till'].''));
	$login = db_escape(trim($_REQUEST['login'].''));
	$passwd = db_escape(trim($_REQUEST['passwd'].''));
	$sn = db_escape(trim($_REQUEST['sn'].''));
	$multivote = $_REQUEST['multivote']+0 ? 't' : 'f';
	
	if ($sn !== '') {
		$data = db_get(
			"insert into voting.votings".
			" (active,descr,since,till,login,passwd,multivote,sn)".
			" values".
			" ('t','$descr','$since','$till','$login','$passwd','$multivote','$sn')".
			" returning id"
		);
		if ($data) {
			$id = $data[0]['id'];
			if ($id) {
				$older = db_get("select id from voting.votings where sn='$sn' order by id limit 1");
				if ($older) {
					$older_id = $older[0]['id'];
					if ($older_id) {
						db("insert into voting.replies(voting_id,answer_id,reply) select $id,answer_id,reply from voting.replies where voting_id=$older_id and answer_id in (-10,-2,-1,0)");
					}
				}
			}
		}
	}
	
	webgui_raw($raw);
}

function action_votingsms_vupdate () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	$descr = db_escape(trim($_REQUEST['descr'].''));
	$since = db_escape(trim($_REQUEST['since'].''));
	$till = db_escape(trim($_REQUEST['till'].''));
	$login = db_escape(trim($_REQUEST['login'].''));
	$passwd = db_escape(trim($_REQUEST['passwd'].''));
	$sn = db_escape(trim($_REQUEST['sn'].''));
	$multivote = $_REQUEST['multivote']+0 ? 't' : 'f';
	$active = $_REQUEST['active']+0 ? 't' : 'f';
	
	$voting_ok = db_escape(trim($_REQUEST['voting_ok'].''));
	$voting_err = db_escape(trim($_REQUEST['voting_err'].''));
	$voting_fail = db_escape(trim($_REQUEST['voting_fail'].''));
	$voting_help = db_escape(trim($_REQUEST['voting_help'].''));
	
	if ($id) {
		db(
			"update voting.votings set".
			" active='$active',descr='$descr',".
			" since='$since',till='$till',sn='$sn',".
			" login='$login',passwd='$passwd',multivote='$multivote'".
			" where id=$id"
		);
		
		$data = db_get("select id from voting.votings where sn='$sn'");
		$ids = array();
		if ($data) {
			foreach ($data as $rec) {
				$ids[] = $rec['id'];
			}
		}
		
		// Common replies by SN: ERROR and HELP
		
		db("delete from voting.replies where voting_id in (".implode(',',$ids).") and answer_id in (-1,-10)");
		foreach ($ids as $vid) {
			if ($voting_err !== '')
				db("insert into voting.replies (voting_id,answer_id,reply) values ($vid,-1,'$voting_err')");
			if ($voting_help !== '')
				db("insert into voting.replies (voting_id,answer_id,reply) values ($vid,-10,'$voting_help')");
		}
		
		// Unique replies per voting: FAIL and DEFAULT OK
		
		db("delete from voting.replies where voting_id=$id and answer_id in (0,-2)");
		if ($voting_ok !== '')
			db("insert into voting.replies (voting_id,answer_id,reply) values ($id,0,'$voting_ok')");
		if ($voting_fail !== '')
			db("insert into voting.replies (voting_id,answer_id,reply) values ($id,-2,'$voting_fail')");

	}
	
	webgui_raw($raw);
}

function action_votingsms_vdelete () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	if ($id) {
		db("delete from voting.votings where id=$id");
		db("delete from voting.replies where voting_id=$id");
	}
		
	webgui_raw($raw);
}

function action_votingsms_vactive () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$active = $_REQUEST['active']+0 ? 't' : 'f';
	
	if ($id) {
		$raw = 'Database Error';
		$data = db_get("update voting.votings set active='$active' where id=$id returning active");
		if ($data) {
			$raw = tpl( 
				$data[0]['active']=='t' ? 'vs_active' : 'vs_inactive',
				array('id'=>$id)
			);
		}
	}
	
	webgui_raw($raw);
}


function action_votingsms_ainsert () {
	$raw = 'DONE';
	
	$voting_id = $_REQUEST['voting_id']+0;
	$descr = db_escape(trim($_REQUEST['descr'].''));
	$keyword = db_escape(trim($_REQUEST['keyword'].''));
	$reply_ok = db_escape(trim($_REQUEST['reply_ok'].''));
	
	if ($voting_id) {
		$ans = db_get("insert into voting.answers (descr,keyword,voting_id,num_votes) values ('$descr','$keyword',$voting_id,0) returning id");
		$ans_id = 0;
		if ($ans) $ans_id = $ans[0]['id'];
		if ($ans_id)
			if ($reply_ok != '') 
				db(
					"insert into voting.replies (voting_id,answer_id,reply)".
					" values ($voting_id,$ans_id,'$reply_ok')"
				);
	}
	
	webgui_raw($raw);
}

function action_votingsms_aupdate () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	$descr = db_escape(trim($_REQUEST['descr'].''));
	$keyword = db_escape(trim($_REQUEST['keyword'].''));
	$reply_ok = db_escape(trim($_REQUEST['reply_ok'].''));
	
	if ($id) {
		$vot = db_get("update voting.answers set descr='$descr',keyword='$keyword' where id=$id returning voting_id");
		if ($vot) {
			$voting_id = $vot[0]['voting_id'];
			db("delete from voting.replies where voting_id=$voting_id and answer_id=$id");
			if ($reply_ok != '') 
				db(
					"insert into voting.replies (voting_id,answer_id,reply)".
					" values ($voting_id,$id,'$reply_ok')"
				);
		}
	}
	
	webgui_raw($raw);
}

function action_votingsms_adelete () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	if ($id) {
		db("delete from voting.answers where id=$id");
		db("delete from voting.replies where answer_id=$id");
	}
	
	webgui_raw($raw);
}

