<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         Subscribers / Subscriptions Admin module
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id$

function smsnews_log ($event='generic', $message='', $subscriber_id=0) {

	global $USER_LOGIN;

	$user = $USER_LOGIN;
	$event = db_escape($event);
	$message = db_escape("[$user] $message");
	$subscriber_id += 0;
	db(
		"insert into smsnews.log (event,message,subscriber_id)".
		" values ('$event','$message',$subscriber_id)"
	);
}

function smsnews_selector_topic ($topic_id=0, $allow_any=0, $multiple=0, $exclude=array()) {
	$group_id = -1;
	$open = false;
	$code = 
		'<select name="'.($multiple ? 'topics[]' : 'topic_id').'"'.
		($multiple ? ' multiple="1" size="5"' : '').
		'>';
	if ($allow_any) {
		$code .= '<option value="0"'.($topic_id ? '' : ' selected').'>---</option>';
	}
	
//	if(is_array($topic_id)) $code.='<optgroup label="'.serialize($topic_id).'"></optgroup>';
		
	$data = db_get("select smsnews.topics.id, smsnews.topics.group_id, smsnews.topics.topic, smsnews.topics.code, smsnews.topic_groups.groupname from smsnews.topics left outer join smsnews.topic_groups on smsnews.topics.group_id=smsnews.topic_groups.id order by smsnews.topic_groups.groupname, smsnews.topics.priority, smsnews.topics.topic");
	
	if ($data) {
		foreach ($data as $rec) {
			if ($rec['topic'] === 'check') {
				// check skipped
			} else if (in_array($rec['id'],$exclude)) {
				// excluded
			} else {
				if ($rec['group_id']!=$group_id) {
					if ($open) {
						$code .= '</optgroup>';
					}
					$open = true;
					$code .= '<optgroup label="'.htmlspecialchars($rec['groupname']).'">';
					$group_id = $rec['group_id'];
				}
				if (is_array($topic_id)) {
					$code .= '<option value="'.$rec['id'].'"'.(in_array($rec['id'].'',$topic_id)  ? ' selected' : '').'>'.htmlspecialchars($rec['code']).' : '.htmlspecialchars($rec['topic']).'</option>';
				} else {
					$code .= '<option value="'.$rec['id'].'"'.("$topic_id" == ''.$rec['id'] ? ' selected' : '').'>'.htmlspecialchars($rec['code']).' : '.htmlspecialchars($rec['topic']).'</option>';
				}
			}
		}
		if ($open) {
			$code .= '</optgroup>';
		}
	}
	
	$code .= '</select>';
	return $code;
}

function smsnews_load_hashes () {
	global $smsnews_topics, $smsnews_categories;
	
	$smsnews_topics = array();
	$smsnews_categories = array();
	
	$data = db_get("select smsnews.topics.id, smsnews.topics.topic, smsnews.topics.code, smsnews.topic_groups.groupname from smsnews.topics left outer join smsnews.topic_groups on smsnews.topics.group_id=smsnews.topic_groups.id");
	if ($data) {
		foreach ($data as $rec) {
			$smsnews_topics[$rec['id']] = htmlspecialchars($rec['groupname']).' / '.htmlspecialchars($rec['topic']).' ('.htmlspecialchars($rec['code']).')';
		}
	}
	
	$data = db_get("select id,category from smsnews.categories");
	if ($data) {
		foreach ($data as $rec) {
			$smsnews_categories[$rec['id']] = htmlspecialchars($rec['category']);
		}
	}
}

function action_smsnews_subslist () {
	global $smsnews_categories;
	smsnews_load_hashes();
	
	$select_cat = selector('category_id',$smsnews_categories,0,'0','---');
	$select_scat = selector('category_id',$smsnews_categories,0);
	$select_topic = smsnews_selector_topic(0,true);

	return tpl('smsnews_subscribers',array(
		'select_cat' => $select_cat,
		'select_scat' => $select_scat,
		'select_topic' => $select_topic
	));
}

function action_smsnews_subsresult () {
	$page = $_REQUEST['page']+0;
	$msisdn = trim($_REQUEST['msisdn'].'');
	$category_id = $_REQUEST['category_id']+0;
	$status = $_REQUEST['status'].'';
	$topic_id = $_REQUEST['topic_id']+0;
	$sub_status = $_REQUEST['sub_status'].'';
	$stopped = $_REQUEST['stopped'].'';
	$name = trim($_REQUEST['name'].'');

	$where = '';
	$cond = array();
	$msisdn = preg_replace('/\D+/','',$msisdn); // Digits only
	if ($msisdn != '') $cond[] = "bers.msisdn::varchar like '%$msisdn%'";
	if ($name != '') $cond[] = "bers.name like '%$name%'";
	if ($category_id) $cond[] = "bers.category_id=$category_id";
	if ($status != '') $cond[] = "bers.status='".db_escape($status)."'";
	if ($topic_id) $cond[] = "tions.topic_id=$topic_id";
	if ($sub_status != '') $cond[] = "tions.status='".db_escape($sub_status)."'";
	if ($stopped != '') $cond[] = "(tions.stopped > '".db_escape($stopped)."'::date and tions.stopped < '".db_escape($stopped)."'::date+interval '1 day')";
	if ($cond) $where = 'where '.implode(' and ',$cond);
	
	$raw = tpl('smsnews_subscribers_empty');
	
	$data = db_get("select count(*) as cnt from smsnews.subscribers as bers left outer join smsnews.subscriptions as tions on bers.id=tions.subscriber_id $where");
	if ($data) {
		$total = $data[0]['cnt'];
		$limit = 50;
		$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
		$start = $offset + 1;
		$end = $offset + $limit; if ($end > $total) $end = $total; // UI
	
		if ($total) {
			$data = db_get(
				"select ".
					"bers.id as uid, bers.msisdn, bers.name, bers.category_id, bers.created::date, ".
					"bers.usage_days, bers.test_until::date, bers.status as ustatus, ".
					"bers.comments, ".
					"tions.id as sid, tions.topic_id, tions.started::date, ".
					"tions.stopped::date, tions.payment, tions.status as sstatus, tions.sn ".
				"from ".
					"smsnews.subscribers as bers left outer join smsnews.subscriptions as tions ".
					"on bers.id=tions.subscriber_id ".
					$where.
				" order by ".
					"bers.id desc, tions.stopped desc ".
				"limit $limit offset $offset"
			);
			
			if ($data) {
				smsnews_load_hashes();
				global $smsnews_topics, $smsnews_categories;
				
				$items = '';
				$user_id = -1;
				
				$ustpl = array(
					'ACTIVE' => tpl('subscriber_status_active'),
					'INACTIVE' => tpl('subscriber_status_inactive')
				);
				$sstpl = array(
					'ACTIVE' => tpl('ss_active'),
					'INACTIVE' => tpl('ss_inactive'),
					'PENDING' => tpl('ss_pending')
				);
				
				foreach ($data as $rec) {
					if ($rec['uid'] != $user_id) {
						$items .= tpl('smsnews_subscribers_subscriber',array(
							'id' => $rec['uid'],
							'status' => isset($ustpl[$rec['ustatus']])
								? $ustpl[$rec['ustatus']]
								: '['.$rec['ustatus'].']',
							'msisdn' => $rec['msisdn'],
							'name' => htmlspecialchars($rec['name']), 
							'category' => $smsnews_categories[$rec['category_id']],
							'created' => $rec['created'],
							'usage_days' => $rec['usage_days'],
							'comments' => preg_replace('/\n/','<br \/>',htmlspecialchars($rec['comments'])),
							'test_until' => $rec['test_until']
						));
						$user_id = $rec['uid'];
					}
					
					if ($rec['sid']) { // Can be NULL if the subscriber has no subscriptions.
						$items .= tpl('smsnews_subscription',array(
							'id' => $rec['sid'],
							'status' => isset($sstpl[$rec['sstatus']])
								? $sstpl[$rec['sstatus']]
								: '['.$rec['sstatus'].']',
							'topic' => $smsnews_topics[$rec['topic_id']],
							'started' => $rec['started'],
							'stopped' => $rec['stopped'],
							'sn' => $rec['sn'],
							'payment' => htmlspecialchars($rec['payment'])
						));
					}
				}
				
				$way_back = $page ? tpl('smsnews_subscribers_back') : '';
				$way_forward = $end < $total ? tpl('smsnews_subscribers_forward') : '';
				$raw = tpl('smsnews_subscribers_list', array(
					'way_back' => $way_back,
					'way_forward' => $way_forward,
					'items' => $items,
					'start' => $start,
					'end' => $end,
					'total' => $total
				));
			}
		}
	}
	
	webgui_raw($raw);
} 

function action_smsnews_subsuserstatus () {
	$id = $_REQUEST['id']+0;
	$raw = '[error]';
	
	if ($id) {
		$ustpl = array(
			'ACTIVE' => tpl('subscriber_status_active'),
			'INACTIVE' => tpl('subscriber_status_inactive')
		);
		$data = db_get("select status from smsnews.subscribers where id=$id");
		if ($data) {
			$status = $data[0]['status'];
			if ($status == 'ACTIVE') {
				$status = 'INACTIVE';
			} else {
				$status = 'ACTIVE';
			}
			$raw = $ustpl[$status];
			db("update smsnews.subscribers set status='$status' where id=$id");
			smsnews_log('subscriber status change',"status=$status",$id);
		}
	}
	
	webgui_raw($raw);
}

function action_smsnews_subsusereditform () {
	$id = $_REQUEST['id']+0;
	$raw = 'ERROR';
	
	if ($id) {
		$data = db_get("select msisdn, name, category_id, test_until::date, comments from smsnews.subscribers where id=$id");
		if ($data) {
			global $smsnews_categories;
			smsnews_load_hashes();
			$select_cat = selector('category_id',$smsnews_categories,$data[0]['category_id']);
			
			$raw = tpl('smsnews_subscribers_edit',array(
				'id' => $id,
				'msisdn' => $data[0]['msisdn'],
				'name' => $data[0]['name'],
				'select_cat' => $select_cat,
				'test_until' => $data[0]['test_until'],
				'comments' => htmlspecialchars($data[0]['comments'])
			));
		}
	}
	
	webgui_raw($raw);
}


function action_smsnews_subsuserupdate () {
	$id = $_REQUEST['id']+0;
	$msisdn = preg_replace('/\D+/','',$_REQUEST['msisdn'].'');
	$category_id = $_REQUEST['category_id']+0;
	$test_until = trim($_REQUEST['test_until'].'');
	$comments = db_escape($_REQUEST['comments'].'');
	$name = db_escape($_REQUEST['name'].'');
	
	if (!$id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if ($msisdn == '') webgui_raw('ERROR: '.tpl('ssu_no_msisdn'));
	if (!$category_id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if ($test_until == '') {
		$test_until = 'NULL';
	} else if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$test_until)) {
		webgui_raw('ERROR: '.tpl('ssu_bad_date'));
	} else {
		$test_until = "'$test_until'";
	}
	
	db("update smsnews.subscribers set msisdn=$msisdn, name='$name', category_id=$category_id, test_until=$test_until, comments='$comments' where id=$id");
	
	smsnews_log('subscriber updated',"msisdn=$msisdn, name=$name, category_id=$category_id, test_until=$test_until, comments=$comments",$id);
	
	$data = db_get("select msisdn, name, category_id, created::date, usage_days, test_until::date, status, comments from smsnews.subscribers where id=$id");
	if ($data) {
		$rec = $data[0];
		smsnews_load_hashes();
		global $smsnews_categories;

		$ustpl = array(
			'ACTIVE' => tpl('subscriber_status_active'),
			'INACTIVE' => tpl('subscriber_status_inactive')
		);
		webgui_raw(tpl('smsnews_subscribers_subscriber',array(
			'id' => $id,
			'status' => isset($ustpl[$rec['status']])
				? $ustpl[$rec['status']]
				: '['.$rec['status'].']',
			'msisdn' => $rec['msisdn'],
			'name'=> htmlspecialchars($rec['name']),
			'category' => $smsnews_categories[$rec['category_id']],
			'created' => $rec['created'],
			'usage_days' => $rec['usage_days'],
			'comments' => preg_replace('/\n/','<br \/>',htmlspecialchars($rec['comments'])),
			'test_until' => $rec['test_until']
		)));
	} else {
		webgui_raw('ERROR: '.tpl('ssu_load_error'));
	}
}


function action_smsnews_subsuseradd () {
	$msisdn = preg_replace('/\D+/','',$_REQUEST['msisdn'].'');
	$category_id = $_REQUEST['category_id']+0;
	$test_until = trim($_REQUEST['test_until'].'');
	$comments = db_escape($_REQUEST['comments'].'');
	$name = db_escape($_REQUEST['name'].'');
	
	if ($msisdn == '') webgui_raw('ERROR: '.tpl('ssu_no_msisdn'));
	if (!$category_id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if ($test_until == '') {
		$test_until = 'NULL';
	} else if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$test_until)) {
		webgui_raw('ERROR: '.tpl('ssu_bad_date'));
	} else {
		$test_until = "'$test_until'";
	}
	
	$data = db_get("insert into smsnews.subscribers (msisdn,name,category_id,test_until,comments,status) values ($msisdn,'$name',$category_id,$test_until,'$comments','ACTIVE') returning id");
	
	if ($data) {
		$id = $data[0]['id'];
		smsnews_log('subscriber added',"msisdn=$msisdn, name=$name, category_id=$category_id, test_until=$test_until, comments=$comments",$id);

		webgui_raw('OK '.$id);
	} else {
		webgui_raw('ERROR: '.tpl('ssu_load_error'));
	}
}



function action_smsnews_subssubscribeform () {
	$subscriber_id = $_REQUEST['id']+0;
	
	$exclude = array();
	$data = db_get("select topic_id from smsnews.subscriptions where subscriber_id=$subscriber_id");
	if ($data) {
		foreach ($data as $rec) {
			$exclude[] = $rec['topic_id'];
		}
	}
	
	$select_topic = smsnews_selector_topic(0,0,0,$exclude);
	
	if (!$subscriber_id) webgui_raw('<!-- No Id Specified -->');

	$started = '07.11.1917';
	$stopped = '25.12.2012';
	$data = db_get("select now()::date as started, (now() + interval '1 month')::date as stopped");
	if ($data) {
		$started = $data[0]['started'];
		$stopped = $data[0]['stopped'];
	}

	webgui_raw(tpl('smsnews_subscribe_form',array(
		'subscriber_id' => $subscriber_id,
		'select_topic' => $select_topic,
		'stopped' => $stopped,
		'started' => $started,
		'sn' => '0',
	)));
}

function action_smsnews_subscribe () {
	$subscriber_id = $_REQUEST['subscriber_id']+0;
	$topic_id = $_REQUEST['topic_id']+0;
	$stopped = trim($_REQUEST['stopped'].'');
	$started = trim($_REQUEST['started'].'');
	$payment = $_REQUEST['payment'].'';
	$sn = $_REQUEST['sn']+0;
	$status = trim($_REQUEST['status'].'');

	$allowed = array('ACTIVE','INACTIVE','PENDING');

	if (!$subscriber_id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if (!$topic_id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$stopped) || !preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$started)) {
		webgui_raw('ERROR: '.tpl('ssu_bad_date'));
	}
	if (!in_array($status,$allowed)) $status = 'PENDING';
	
	$data = db_get(
		"insert into smsnews.subscriptions (subscriber_id,topic_id,created,started,stopped,status,sn,payment) ".
		"values ($subscriber_id,$topic_id,now(),'$started','$stopped','$status','$sn', '".db_escape($payment)."') ".
		"returning id"
	);
	if ($data) {
		$id = $data[0]['id'];
		
		smsnews_log('subscription added',"topic_id=$topic_id, started=$started, stopped=$stopped, status=$status",$subscriber_id);
		
		$data = db_get(
			"select status,topic_id,started::date,stopped::date,sn,payment ".
			"from smsnews.subscriptions where id=$id"
		);
		if ($data) {
			smsnews_load_hashes();
			global $smsnews_topics;
			$rec = $data[0];
			$sstpl = array(
				'ACTIVE' => tpl('ss_active'),
				'INACTIVE' => tpl('ss_inactive'),
				'PENDING' => tpl('ss_pending')
			);

			webgui_raw(tpl('smsnews_subscription',array(
				'id' => $id,
				'status' => isset($sstpl[$rec['status']])
					? $sstpl[$rec['status']]
					: '['.$rec['status'].']',
				'topic' => $smsnews_topics[$rec['topic_id']],
				'started' => $rec['started'],
				'stopped' => $rec['stopped'],
				'sn' => $rec['sn'],
				'payment' => htmlspecialchars($rec['payment'])
			)));
		}
	}
	
	webgui_raw('ERROR: '.tpl('ssu_load_error'));
}


function action_smsnews_ssupdate () {
	$id = $_REQUEST['id']+0;
	$topic_id = $_REQUEST['topic_id']+0;
	$stopped = trim($_REQUEST['stopped'].'');
	$started = trim($_REQUEST['started'].'');
	$payment = $_REQUEST['payment'].'';
	$status = trim($_REQUEST['status'].'');
	$sn = $_REQUEST['sn']+0;

	$allowed = array('ACTIVE','INACTIVE','PENDING');

	if (!$id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if (!$topic_id) webgui_raw('ERROR: '.tpl('ssu_bad_call'));
	if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$stopped) || !preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$started)) {
		webgui_raw('ERROR: '.tpl('ssu_bad_date'));
	}
	if (!in_array($status,$allowed)) $status = 'PENDING';

	db(
		"update smsnews.subscriptions ".
		"set status='$status', started='$started', stopped='$stopped', topic_id=$topic_id, sn='$sn', payment='".db_escape($payment)."' ".
		"where id=$id"
	);
	
	$data = db_get(
		"select subscriber_id,status,topic_id,started::date,stopped::date,sn,payment ".
		"from smsnews.subscriptions where id=$id"
	);
	if ($data) {
		
		smsnews_log('subscription updated',"topic_id=$topic_id, started=$started, stopped=$stopped, status=$status",$data[0]['subscriber_id']);
		
		smsnews_load_hashes();
		global $smsnews_topics;
		$rec = $data[0];
		$sstpl = array(
			'ACTIVE' => tpl('ss_active'),
			'INACTIVE' => tpl('ss_inactive'),
			'PENDING' => tpl('ss_pending')
		);

		webgui_raw(tpl('smsnews_subscription',array(
			'id' => $id,
			'status' => isset($sstpl[$rec['status']])
				? $sstpl[$rec['status']]
				: '['.$rec['status'].']',
			'topic' => $smsnews_topics[$rec['topic_id']],
			'started' => $rec['started'],
			'stopped' => $rec['stopped'],
			'sn' => $rec['sn'],
			'payment' => htmlspecialchars($rec['payment'])
		)));
	}
	
	webgui_raw('ERROR: '.tpl('ssu_load_error'));
}

function action_smsnews_subssubscreditform () {
	$id = $_REQUEST['id']+0;
	
	if ($id) {
		$data = db_get(
			"select subscriber_id, status, topic_id, started::date, stopped::date, sn, payment ".
			"from smsnews.subscriptions where id=$id"
		);
		if ($data) {
			$rec = $data[0];

			$exclude = array();
			$data = db_get(
				"select topic_id from smsnews.subscriptions where subscriber_id=".$rec['subscriber_id']
			);
			if ($data) {
				foreach ($data as $row) {
					if ($row['topic_id'] != $rec['topic_id'])
						$exclude[] = $row['topic_id'];
				}
			}

			$select_topic = smsnews_selector_topic($rec['topic_id'],0,0,$exclude);
			
			webgui_raw(tpl('smsnews_subscribe_editform',array(
				'id' => $id,
				'status' => $rec['status'],
				'select_topic' => $select_topic,
				'started' => $rec['started'],
				'stopped' => $rec['stopped'],
				'sn' => $rec['sn'],
				'payment' => $rec['payment']
			)));
		}
	}
	
	webgui_raw('ERROR');
}


