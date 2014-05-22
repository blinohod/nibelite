<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         Messages management
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id$

/* init_* functions are called automagically from webgui dispatcher 
 * at init stage.
 * there we are splitting templates
 */
function init_smsnews_messages () {
	global $LANGUAGE;
	read_templates(TEMPLATES.'design.smsnews_messages.'.$LANGUAGE.'.html');
}

function action_smsnews_messages () {
	global $smsnews_categories, $USER_LOGIN;
	smsnews_load_hashes(); # assuming smsnews_subscribers.web2.php also included
	
	$user = $USER_LOGIN;

	return tpl('smsnews_messages',array(
		'user' => $user,
		'select_cat' => selector('category_id',$smsnews_categories,'0','0','---')
	));
}

function action_smsnews_msglist () {
	$page = $_REQUEST['page']+0;
	$category_id = $_REQUEST['category_id']+0;
	$status = $_REQUEST['status'].'';
	$sort = $_REQUEST['sort'].''; if ($sort != 'asc') $sort = 'desc';
	
	$st = array(
		'NEW' => tpl('msg_status_new'),
		'QUEUED' => tpl('msg_status_queued'),
		'SENT' => tpl('msg_status_sent'),
		'CANCELED' => tpl('msg_status_canceled')
	);

	$where = '';
	$cond = array();
	if ($category_id) {
		$cond[] = "category_id=$category_id";
	} else {
		$data = db_get("select id from smsnews.categories where category='check'");
		if ($data) {
			$cond[] = "category_id!=".$data[0][id];
		}
	}
	if ($status != '') $cond[] = "status='".db_escape($status)."'";
	if ($cond) $where = 'where '.implode(' and ',$cond);

	$raw = tpl('msg_list_empty');
	
	$data = db_get("select count(*) as cnt from smsnews.queue $where");
	if ($data) {
		$total = $data[0]['cnt'];
		
		if ($total) {
			$limit = 20;
			$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
			$start = $offset + 1;
			$end = $offset + $limit; if ($end > $total) $end = $total; // UI

			global $smsnews_categories, $smsnews_topics;
			smsnews_load_hashes();
		
			$nav = tpl('msg_list_nav',array(
				'way_back' => $page>0 ? tpl('msg_list_back') : '',
				'way_forward' => $end<$total ? tpl('msg_list_forward') : '',
				'start' => $start,
				'end' => $end,
				'total'=> $total
			));
		
			$raw = $total > 7 ? $nav : '';
			
			$data = db_get(
				"select id, status, send_time, category_id, topics, creator, ".
				"substr(msg_body,1,40) as snippet ".
				"from smsnews.queue $where order by send_time $sort limit $limit offset $offset"
			);
			if ($data) {
				foreach ($data as $rec) {
					$topics = '';
					if (preg_match_all('/(\d+)/',$rec['topics'],$m,PREG_PATTERN_ORDER)) {
						foreach ($m[1] as $topic_id) {
							$topics .= tpl('msg_li_topic',array(
								'topic' => $smsnews_topics[$topic_id]
							));
						}
					}
					
					$raw .= tpl('msg_list_item', array(
						'id' => $rec['id'],
						'status_img' => 
							isset($st[$rec['status']]) 
							? $st[$rec['status']] 
							: '['.$rec['status'].']',
						'send_time' => substr($rec['send_time'],0,16),
						'category' => $smsnews_categories[$rec['category_id']],
						'topics' => $topics,
						'creator' => htmlspecialchars($rec['creator']), 
						'text' => $rec['snippet'].(strlen($rec['snippet'])>=40 ? '...' : '')
					));
				}
			}
			
			$raw .= $total > $limit ? $nav : '';
		}
	}
	
	webgui_raw($raw);
}


function action_smsnews_msgnew() {

	global $USER_LOGIN;

	$data = db_get("select id from smsnews.categories order by id limit 1");
	if ($data) {
		$category_id = $data[0]['id'];
		$creator = db_escape($USER_LOGIN);
		$data = db_get(
			"insert into smsnews.queue (creator,send_time,topics,category_id) ".
			"values('$creator',now(),'{}',$category_id) ".
			"returning id"
		);
		if ($data) {
			$id = $data[0]['id'];
			if ($id) {
				smsnews_log('message added',"id=$id, category_id=$category_id");
				webgui_raw('{"id":'.$id.'}');
			}
		}
	}
	
	webgui_raw('ERROR');
}

function action_smsnews_msgclone() {

	global $USER_LOGIN;

	$id = $_REQUEST['id']+0;
	
	if ($id) {
		$creator = db_escape($USER_LOGIN);
		$data = db_get(
			"insert into smsnews.queue (creator,send_time,topics,category_id,priority,msg_type,msg_body,extra,coding) ".
			"select '$creator',send_time,topics,category_id, priority,msg_type,msg_body,extra,coding from smsnews.queue where id=$id ".
			"returning id"
		);
		if ($data) {
			$idnew = $data[0]['id'];
			if ($idnew) {
				smsnews_log('message cloned',"id=$id, idnew=$idnew");
				webgui_raw('{"id":'.$idnew.'}');
			}
		}
	}
	
	webgui_raw('ERROR');
}

function action_smsnews_msgreset() {

	$id = $_REQUEST['id']+0;
	
	if ($id) {
		$data = db_get(
			"update smsnews.queue set status='NEW' ".
			"where id=$id and (status='QUEUED' or status='CANCELED') ".
			"returning id"
		);
		if ($data) {
			$id = $data[0]['id'];
			if ($id) {
				smsnews_log('message reset',"id=$id");
				webgui_raw('{"id":'.$id.'}');
			}
		}
	}
	
	webgui_raw('ERROR');
}

function action_smsnews_msgcancel() {

	$id = $_REQUEST['id']+0;
	
	if ($id) {
		$data = db_get(
			"update smsnews.queue set status='CANCELED' ".
			"where id=$id and (status='QUEUED' or status='NEW') ".
			"returning id"
		);
		if ($data) {
			$id = $data[0]['id'];
			if ($id) {
				smsnews_log('message canceled',"id=$id");
				webgui_raw('{"id":'.$id.'}');
			}
		}
	}
	
	webgui_raw('ERROR');
}


function action_smsnews_msgedit() {
	$id = $_REQUEST['id']+0;
	
	if ($id) {
		$data = db_get(
			"select ".
				"msg_id,created,creator,send_time,topics,category_id,".
				"priority,msg_type,msg_body,extra,status,num_subs,".
				"num_sms,num_test,coding ".
			"from smsnews.queue ".
			"where id=$id"
		);
		if ($data) {
			$msg = $data[0];
			$out = $msg;
			$raw = '';
			
			$st = array(
				'NEW' => 'msg_status_new',
				'QUEUED' => 'msg_status_queued',
				'SENT' => 'msg_status_sent',
				'CANCELED' => 'msg_status_canceled'
			);
			$et = array(
				'NEW' => 'msg_edittop_new',
				'QUEUED' => 'msg_edittop_queued',
				'SENT' => 'msg_edittop_sent',
				'CANCELED' => 'msg_edittop_canceled'
			);
			
			$out['send_time'] = substr($msg['send_time'],0,16);
			$out['id'] = $id;
			
			$raw .= tpl(
				isset($et[$msg['status']]) 
					? $et[$msg['status']] 
					: 'msg_edittop_unknown',
				array(
					'id' => $id
				)
			);

			global $smsnews_categories, $smsnews_topics;
			smsnews_load_hashes();
			
			if ($msg['status']=='NEW') {
				// Show the edit form
				
				//$t = explode(' ',$out['send_time']);
				//$out['send_date'] = $t[0];
				//$out['send_time'] = $t[1];
				
				$out['msg_body'] = htmlspecialchars($msg['msg_body']);
				$out['select_priority'] = selector(
					'priority',
					array(
						'0' => tpl('msg_priority_0'),
						'1' => tpl('msg_priority_1')
					),
					($msg['priority'] ? '1' : '0')
				);
				$out['select_coding'] = tpl('msg_select_coding',array(
					'checked0' => $msg['coding'] ? '' : 'checked="1"',
					'checked2' => $msg['coding'] ? 'checked="1"' : ''
				));
				$out['select_category'] = selector('category_id',$smsnews_categories,$msg['category_id']);
				
				$topics = array();
				if (preg_match_all('/(\d+)/',$msg['topics'],$m,PREG_PATTERN_ORDER)) $topics = $m[1];
				$out['select_topic'] = smsnews_selector_topic($topics,0,true);
				
				$out['web_status'] = isset($st[$msg['status']]) 
					? tpl($st[$msg['status']]).' '.$msg['status'] 
					: '['.$msg['status'].']';
				
				$raw .= tpl('msg_edit_form',$out);
			} else {
				// Just show the message data
				
				$out['topics'] = '';
				if (preg_match_all('/(\d+)/',$msg['topics'],$m,PREG_PATTERN_ORDER)) {
					foreach ($m[1] as $topic_id) {
						$out['topics'] .= tpl('msg_show_topic',array(
							'topic' => $smsnews_topics[$topic_id]
						));
					}
				}
				$out['category'] = $smsnews_categories[$msg['category_id']];
				$out['priority'] = $msg['priority'] ? tpl('msg_priority_1') : tpl('msg_priority_0');
				$out['msg_body'] = preg_replace('/\n/','<br />',htmlspecialchars($msg['msg_body']));
				$out['extra'] = preg_replace('/\n/','<br />',htmlspecialchars($msg['extra']));
				$out['status'] = isset($st[$msg['status']]) 
					? tpl($st[$msg['status']]).' '.$msg['status'] 
					: '['.$msg['status'].']';
				$out['coding'] = $msg['coding'] ? 'CYRILLIC (2)' : 'LATIN (0)';
				
				$raw .= tpl('msg_edit_show',$out);
			}
			
			webgui_raw($raw);
		}
	}
	
	webgui_raw('&nbsp;');
}


function action_smsnews_msgupdate() {

	global $USER_LOGIN;

	$id = $_REQUEST['id']+0;
	$status = $_REQUEST['status'].'';
		if ($status != 'QUEUED') $status = 'NEW';
	$msg_body = db_escape($_REQUEST['msg_body'].'');
	$priority = $_REQUEST['priority']+0;
		if ($priority) $priority=1;
	$coding = $_REQUEST['coding']+0;
		if ($coding) $coding=2;
	$topics = $_REQUEST['topics'];
		if (!is_array($topics)) $topics = array();
		$topics = implode(',',$topics);
	$category_id = $_REQUEST['category_id']+0;
		if (!$category_id) $category_id='category_id+0';
	$send_time = db_escape($_REQUEST['send_time'].'');
	$test = $_REQUEST['test']+0;
	$creator = db_escape($USER_LOGIN);
	
	if ($id) {
		$data = db_get(
			"update smsnews.queue set status='$status', msg_body='$msg_body', priority=$priority, coding=$coding, topics='\{$topics\}', creator='$creator', category_id=$category_id, send_time='$send_time'".
			"where id=$id and status='NEW' ".
			"returning id"
		);
		if ($data) {
			$id = $data[0]['id'];
			smsnews_log('message updated',"id=$id");
			if ($id) {
				if ($test) {
					$testTopic = 0;
					$testCat = 0;
					$data = db_get("select id from smsnews.topics where topic='check'");
					if ($data) $testTopic = $data[0]['id'];
					$data = db_get("select id from smsnews.categories where category='check'");
					if ($data) $testCat = $data[0]['id'];
					if ($testCat && $testTopic) {
						$data = db(
							"insert into smsnews.queue (creator,send_time,topics,category_id,priority,msg_type,msg_body,extra,coding,status) ".
							"select creator,now(),'\{$testTopic\}',$testCat, priority,msg_type,msg_body,extra,coding,'QUEUED' from smsnews.queue where id=$id"
						);
						smsnews_log('message test',"id=$id");
					} else {
						webgui_raw("Error: Test Topic is [$testTopic]; Test Category is [$testCat]");
					}
				}
				webgui_raw('{"id":'.$id.'}');
			}
		}
	}
	
	webgui_raw('ERROR');
}
