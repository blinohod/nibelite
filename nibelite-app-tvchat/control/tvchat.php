<?php
/* Total remake by Anatoly Matyakh <protopartorg@gmail.com> 
 * All business logic is implemented below - no unique includes 
 */

// Find installation root and initialize all this stuff.
if (getenv('NIBELITE_HOME')) {
  include_once(getenv('NIBELITE_HOME')."/etc/nibelite.init.php");
} else {
  include_once("/opt/nibelite/etc/nibelite.init.php");
}

include_once SYS . '/lib/php/webgui.inc.php'; 

face_control('tvchat');

function init_tvchat() {
	global $_PAGE, $LANGUAGE;

	read_templates(TEMPLATES.'design.tvchat.'.$LANGUAGE.'.html');

	$_PAGE = array(
		'page_status' => '',
		'page_title' => translate('tvchat_title'),
		'page_head' => translate('tvchat_head'),
		'page_menu' => tpl('tvchat_menu'),
		'page_main' => '',
		'page_time' => '',
		'main_menu' => make_main_menu(),
	);

}

function action_default() {
	return action_tvchat_messages();
}

webgui_init();
webgui_run();


function action_tvchat_setup () {
	return tpl('tvcc_manage');
}

/* 
 * Setup Actions
 */

function action_tvchat_chats () {
	$raw = '';
	
	$page = $_REQUEST['page']+0;
	
	$data = db_get("select count(*) as cnt from tvchat.service");
	$raw = 'database error';
	if ($data) {
		$total = $data[0]['cnt'];
		if ($total) {
			$limit = 20;
			$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
			$start = $offset + 1;
			$end = $offset + $limit; if ($end > $total) $end = $total; // UI
		
			$back = $page>0 ? tpl('tvcc_back') : '';
			$next = $end<$total ? tpl('tvcc_more') : '';
		
			$data = db_get(
				"select".
					" s.id, s.active, s.name, s.login, s.passwd, s.sn,".
					" s.pattern, s.reply_ok, s.reply_help,".
					" s.reply_closed, count(c.id) as num_chat".
				" from tvchat.service s left outer join tvchat.chat c on s.id=c.service_id".
				" group by".
					" s.id, s.active, s.name, s.login, s.passwd,".
					" s.sn, s.pattern, s.reply_ok, s.reply_help, s.reply_closed".
				" order by sn, pattern, active desc".
				" limit $limit offset $offset"
			);
			
			if ($data) {
				$list = '';
				
				foreach ($data as $row) {
					// id, active, name, login, passwd, sn
					$chat_id = $row['id'];
					$row['name'] = htmlspecialchars($row['name']);
					$row['login'] = htmlspecialchars($row['login']);
					$row['passwd'] = htmlspecialchars($row['passwd']);
					$row['pattern'] = htmlspecialchars($row['pattern']);
					$row['reply_ok'] = htmlspecialchars($row['reply_ok']);
					$row['reply_help'] = htmlspecialchars($row['reply_help']);
					$row['reply_closed'] = htmlspecialchars($row['reply_closed']);
					$row['actcheck'] = $row['active']=='t' ? 'checked="1"' : '';
					$row['active'] = tpl( 
						$row['active']=='t' ? 'tvcc_active' : 'tvcc_inactive',
						array('id'=>$chat_id)
					);
					$row['patshow'] = $row['pattern']==='' ? 'default' : $row['pattern'];
					$row['addstyle'] = $row['pattern']==='' ? 'style="border: 3px solid #666"' : '';
					
					$list .= tpl('tvcc_item', array(
						'id' => $chat_id,
						'chat' => tpl('tvcc_chat',$row)
					));
				}
				
				$raw = tpl('tvcc_list',array(
					'total'		=> $total,
					'start'		=> $start,
					'end'		=> $end,
					'back'		=> $back,
					'more'		=> $next,
					'list'		=> $list
				));
			}
		} else {
			$raw = tpl('tvcc_list',array(
				'total'		=> 0,
				'start'		=> 0,
				'end'		=> 0,
				'back'		=> '',
				'more'		=> '',
				'list'		=> tpl('tvcc_empty')
			));
		}
	}
	
	webgui_raw($raw);
}

function action_tvchat_chat_insert () {
	$raw = 'DONE';
	
	$name = db_escape(trim($_REQUEST['name'].''));
	$login = db_escape(trim($_REQUEST['login'].''));
	$passwd = db_escape(trim($_REQUEST['passwd'].''));
	$sn = db_escape(trim($_REQUEST['sn'].''));
	$pattern = db_escape(trim($_REQUEST['pattern'].''));
	$reply_ok = db_escape(trim($_REQUEST['reply_ok'].''));
	$reply_help = db_escape(trim($_REQUEST['reply_help'].''));
	$reply_closed = db_escape(trim($_REQUEST['reply_closed'].''));
	
	if ($name !== '') {
		$data = db(
			"insert into tvchat.service".
			" (active,name,login,passwd,sn,pattern,reply_ok,reply_help,reply_closed)".
			" values".
			" ('t','$name','$login','$passwd','$sn','$pattern','$reply_ok','$reply_help','$reply_closed')"
		);
	}
	
	webgui_raw($raw);
}

function action_tvchat_chat_update () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	$name = db_escape(trim($_REQUEST['name'].''));
	$login = db_escape(trim($_REQUEST['login'].''));
	$passwd = db_escape(trim($_REQUEST['passwd'].''));
	$sn = db_escape(trim($_REQUEST['sn'].''));
	$active = $_REQUEST['active']+0 ? 't' : 'f';
	$pattern = db_escape(trim($_REQUEST['pattern'].''));
	$reply_ok = db_escape(trim($_REQUEST['reply_ok'].''));
	$reply_help = db_escape(trim($_REQUEST['reply_help'].''));
	$reply_closed = db_escape(trim($_REQUEST['reply_closed'].''));
	
	if ($id) {
		db(
			"update tvchat.service set".
			" active='$active',name='$name',sn='$sn',".
			" login='$login',passwd='$passwd',pattern='$pattern',".
			" reply_ok='$reply_ok',reply_help='$reply_help',reply_closed='$reply_closed'".
			" where id=$id"
		);
	}
	
	webgui_raw($raw);
}

function action_tvchat_chat_clone () {
	$raw = 'DONE';
	$id = $_REQUEST['id']+0;
	$pattern = db_escape(trim($_REQUEST['pattern'].''));
	
	//TODO: If we want more complex patterns 
	//      we have to specify escape-string modifier like E'\\w'
	//      for backslashes. I don't see any sence here but Postgres does.
	
	if ($id) {
		db(
			"insert into tvchat.service".
			" (sn,name,active,login,passwd,reply_ok,reply_help,reply_closed,pattern)".
			" select sn,name,active,login,passwd,reply_ok,reply_help,reply_closed,'$pattern'".
			" from tvchat.service where id=$id"
		);
	}
	
	webgui_raw($raw);
}

function action_tvchat_chat_delete () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	if ($id) {
		db("delete from tvchat.service where id=$id");
	}
		
	webgui_raw($raw);
}

function action_tvchat_chat_active () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$active = $_REQUEST['active']+0 ? 't' : 'f';
	
	if ($id) {
		$raw = 'Database Error';
		$data = db_get("update tvchat.service set active='$active' where id=$id returning active");
		if ($data) {
			$raw = tpl( 
				$data[0]['active']=='t' ? 'tvcc_active' : 'tvcc_inactive',
				array('id'=>$id)
			);
		}
	}
	
	webgui_raw($raw);
}

/* CHAT ACTIONS
 */

function action_tvchat_messages () {
	$serv_options = '';
	$opt = db_get("select id,sn,name from tvchat.service where active order by sn,name");
	if ($opt) {
		foreach ($opt as $row) {
			$serv_options .= '<option value="'.htmlspecialchars($row['id']).'">'.htmlspecialchars($row['sn']).' '.htmlspecialchars(substr($row['name'],0,40)).'</option>';
		}
	}

	return tpl('chat_manage',array('serv_options' => $serv_options));
}

function getChatRow ($id) {
	if ($id) {
		$data = db_get(
			"select ".
				"c.id,c.sn,c.msisdn,c.body,".
				"to_char(c.received,'Mon DD HH24:MI') as received,".
				"to_char(c.approved,'Mon DD HH24:MI') as approved,".
				"c.status,c.editor_info,b.id as banned ".
			"from tvchat.chat c".
			" left outer join tvchat.ban b on c.msisdn=b.msisdn and b.till > now()".
			"where c.id=$id"
		);
		if ($data) {
			$colors = array(
				'NEW' => '#c00',
				'APPROVED' => '#0c0',
				'REJECTED' => '#999'
			);
			$row = $data[0];
			$row['body'] = htmlspecialchars($row['body']);
			$row['editor_info'] = htmlspecialchars($row['editor_info']);
			$row['msisdn'] = $row['banned']+0 ? '<s>'.$row['msisdn'].'</s>' : $row['msisdn'];
			$row['color'] = $colors[$row['status']];
			$row['func'] = 
				($row['status']==='NEW')
				? tpl('chat_row_func', array('id'=>$row['id']))
				: '';
			return tpl('chat_row',$row);
		}
	}
	return '';
}

function action_tvchat_chat_list () {
	$page = $_REQUEST['page'] + 0;
	$last_id = $_REQUEST['last_id'] + 0;
	$serv = trim($_REQUEST['serv'].'');
	$status = trim($_REQUEST['status'].'');
	
	$raw = '';
	$where = array();
	if (($serv != 'ALL') and ($serv !== '')) $where[] = "service_id='".db_escape($serv)."'";
	if (($status != 'ALL') and ($status !== '')) $where[] = "status='".db_escape($status)."'";
	
	if ($last_id) {
		$where[] = "id > $last_id";
		$data = db_get("select id from tvchat.chat where ".implode(' and ',$where)." order by id desc");
		if ($data) {
			foreach ($data as $row) {
				$raw .= getChatRow($row['id']);
			}
		}
	} else {
		$wtext = '';
		if ($where) $wtext = 'where '.implode(' and ',$where);
		$data = db_get("select count(*) as cnt from tvchat.chat $wtext");
		$raw = 'no messages';
		if ($data) {
			$total = $data[0]['cnt'];
			
			if ($total) {
				$limit = 50;
				$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
				$start = $offset + 1;
				$end = $offset + $limit; if ($end > $total) $end = $total; // UI
			
				$back = $page>0 ? tpl('tvcc_back') : '';
				$next = $end<$total ? tpl('tvcc_more') : '';
			
				$data = db_get(
					"select id from tvchat.chat ".
					$wtext.
					" order by id desc".
					" limit $limit offset $offset"
				);
				
				if ($data) {
					$list = '';
					
					foreach ($data as $row) {
						$list .= getChatRow($row['id']);
					}
					
					$raw = tpl('chat_list',array(
						'total'		=> $total,
						'start'		=> $start,
						'end'		=> $end,
						'back'		=> $back,
						'more'		=> $next,
						'list'		=> $list
					));
				}
			} else {
				$raw = tpl('chat_list',array(
					'total'		=> 0,
					'start'		=> 0,
					'end'		=> 0,
					'back'		=> '',
					'more'		=> '',
					'list'		=> ''
				));
			}
		}
	}
	
	webgui_raw($raw);
}

function action_tvchat_message_status () {

	global $USER_ID, $USER_LOGIN;

	$id = $_REQUEST['id']+0;
	$status = trim($_REQUEST['status'].'');
	$raw = 'Bad request!';
	
	$allowed = array(
		'APPROVED' => 1,
		'REJECTED' => 1
	);
	
	if ($id) {
		if ($allowed[$status]) {
			$user = $USER_LOGIN;
			$addr = $_SERVER['REMOTE_ADDR'];
			$edit = db_escape("$user / $addr");
			db("update tvchat.chat set status='$status', editor_info='$edit', approved=now() where id=$id");
		}
		$raw = getChatRow($id);
	}
	
	webgui_raw($raw);
}

function action_tvchat_message_ban () {

	global $USER_LOGIN;

	$id = $_REQUEST['id']+0;
	$jail = trim($_REQUEST['jail'].'');
	$raw = 'DONE';
	
	$allowed = array(
		'hour' => 1,
		'day' => 1,
		'month' => 1
	);
	
	if ($id) {
		if ($allowed[$jail]) {
			$user = $USER_LOGIN;
			$addr = $_SERVER['REMOTE_ADDR'];
			$edit = db_escape("Banned by $user / $addr");
			
			$data = db_get("select msisdn,sn,body from tvchat.chat where id=$id");
			if ($data) {
				$msisdn = db_escape($data[0]['msisdn']);
				$sn = db_escape($data[0]['sn']);
				$msg = db_escape(substr($data[0]['body'],0,127));
			
				$data = db_get("insert into tvchat.ban(msisdn,sn,since,till,note) values ('$msisdn','$sn',now(),now() + interval '1 $jail','$msg') returning id");
				if ($data) {
					db("update tvchat.chat set status='REJECTED', editor_info='$edit', approved=now() where status='NEW' and sn='$sn' and msisdn='$msisdn' and id>=$id");
				}
			}
		}
	}
	
	webgui_raw($raw);
}

/* BAN ACTIONS
 */

function action_tvchat_banlist () {
	$serv_options = '';
	$opt = db_get("select sn,name from tvchat.service where active order by sn,name");
	if ($opt) {
		foreach ($opt as $row) {
			$serv_options .= '<option value="'.htmlspecialchars($row['sn']).'">'.htmlspecialchars($row['sn']).' '.htmlspecialchars(substr($row['name'],0,40)).'</option>';
		}
	}

	return tpl('chat_banpage',array('serv_options' => $serv_options));
}

function getBanRow ($id) {
	if ($id) {
		$data = db_get(
			"select ".
				"id,sn,msisdn,note,".
				"to_char(since,'Mon DD HH24:MI') as since,".
				"to_char(till,'Mon DD HH24:MI') as till,".
				"till>now() as fresh ".
			"from tvchat.ban ".
			"where id=$id"
		);
		if ($data) {
			$row = $data[0];
			$row['note'] = htmlspecialchars($row['note']);
			$row['style'] = $row['fresh'] === 'f' ? 'color:#ccc;' : '';
			return tpl('chat_banrow',$row);
		}
	}
	return '';
}

function action_tvchat_ban_list () {
	$page = $_REQUEST['page'] + 0;
	$serv = trim($_REQUEST['serv'].'');
	
	$raw = '';
	$where = '';
	if (($serv !== 'ALL') and ($serv !== '')) $where = "where sn='".db_escape($serv)."'";
	
	$data = db_get("select count(*) as cnt from tvchat.ban $where");
	$raw = 'no messages';
	if ($data) {
		$total = $data[0]['cnt'];
		
		if ($total) {
			$limit = 50;
			$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
			$start = $offset + 1;
			$end = $offset + $limit; if ($end > $total) $end = $total; // UI
		
			$back = $page>0 ? tpl('tvcc_back') : '';
			$next = $end<$total ? tpl('tvcc_more') : '';
		
			$data = db_get(
				"select id from tvchat.ban ".
				$where.
				" order by till desc".
				" limit $limit offset $offset"
			);
			
			if ($data) {
				$list = '';
				
				foreach ($data as $row) {
					$list .= getBanRow($row['id']);
				}
				
				$raw = tpl('chat_banlist',array(
					'total'		=> $total,
					'start'		=> $start,
					'end'		=> $end,
					'back'		=> $back,
					'more'		=> $next,
					'list'		=> $list
				));
			}
		} else {
			$raw = 'No bans';
		}
	}
	
	webgui_raw($raw);
}

function action_tvchat_ban_clear () {
	$id = $_REQUEST['id']+0;
	$raw = 'DONE';
	
	if ($id) {
		db("update tvchat.ban set till=now() where id=$id and till >= now()");
	}
	
	webgui_raw($raw);
}


// Do not close php \?\> tag! Accidentally it can cause "output started..." errors.
