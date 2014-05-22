<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         Topics Admin module
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id: smsnews.webgui.php 159 2008-02-19 11:20:55Z misha $

/* ACTION: smsnews-topiclist
 * Administer SMSNews topics and topic groups
 */
function action_smsnews_topiclist() {
	$table_rows = '';

	if ($data_grp = db_get("select id, groupname from smsnews.topic_groups order by groupname")) {
		foreach ($data_grp as $rec_grp) {
			$table_rows .= tpl('smsnews_topic_group',array(
				'id' => $rec_grp['id'],
				'group' => $rec_grp['groupname']
			));
			
			if ($data = db_get("select id, topic, code, priority from smsnews.topics where group_id=".$rec_grp['id']." order by priority, topic")) {
				foreach ($data as $rec) {
					if ($rec['topic'] != 'check') {
						$table_rows .= tpl('smsnews_topic_row',array(
							'id' => $rec['id'],
							'topic' => htmlspecialchars($rec['topic']),
							'code' => $rec['code'],
							'group_id' => $rec_grp['id'],
							'priority' => $rec['priority']
						));
					}
				}
			}
		}
	}

	return tpl('smsnews_topic_list',array(
		'rows' => $table_rows
	));
}

function action_smsnews_topicaddgroup() {
	$group = $_REQUEST['name'].'';
	$id = 0;
	
	if ($group) {
		$data = db_get("insert into smsnews.topic_groups (groupname) values('".db_escape($group)."') returning id");
		if ($data) {
			$id = $data[0]['id'];
			smsnews_log('topic group added',"id=$id, groupname=$group");
		}
	}
	
	$raw = $id 
		? tpl('smsnews_topic_group',array(
				'id' => $id,
				'group' => htmlspecialchars($group)
			))
		: tpl('smsnews_topic_rowerror');
	
	webgui_raw($raw);
}

function action_smsnews_topicrenamegroup() {
	$group = $_REQUEST['name'].'';
	$id = $_REQUEST['id']+0;
	
	if ($group && $id) {
		db("update smsnews.topic_groups set groupname='".db_escape($group)."' where id=$id");
		smsnews_log('topic group updated',"id=$id, groupname=$group");
	}
	
	$raw = tpl('smsnews_topic_group',array(
				'id' => $id,
				'group' => htmlspecialchars($group)
			));
	
	webgui_raw($raw);
}

function action_smsnews_topicdeletegroup() {
	$id = $_REQUEST['id']+0;
	$raw = 'ERROR';
	
	if ($id) {
		$data = db_get("select count(*) as cnt from smsnews.topics where group_id=$id");
		$notempty = $data[0]['cnt'];
		if ($notempty) {
			$raw = 'NOT EMPTY';
		} else {
			db("delete from smsnews.topic_groups where id=$id");
			$raw = 'DELETED';
			smsnews_log('topic group deleted',"id=$id");
		}
	}
	
	webgui_raw($raw);
}

function action_smsnews_topicadd() {
	$group_id = $_REQUEST['group_id']+0;
	$code = $_REQUEST['code'].'';
	$topic = $_REQUEST['topic'];
	$raw = 'DATA ERROR';
	
	if ($group_id && $code && $topic) {
		$id = 0;
		$data = db_get("insert into smsnews.topics (topic,code,priority,group_id) values ('".db_escape($topic)."','".db_escape($code)."',0,$group_id) returning id");
		if ($data) {
			$id = $data[0]['id'];
		}
		if ($id) {
			smsnews_log('topic added',"id=$id, code=$code, group_id=$group_id, topic=$topic");
			$raw = tpl('smsnews_topic_row',array(
				'id' => $id,
				'topic' => htmlspecialchars($topic),
				'code' => $code,
				'group_id' => $group_id,
				'priority' => 0
			));
		} else {
			$raw = 'ERROR';
		}
	}
	
	webgui_raw($raw);
}

function action_smsnews_topicedit() {
	$id = $_REQUEST['id']+0;
	
	$raw = tpl('smsnews_topic_rowerror');
	
	if ($id) {
		$data = db_get("select topic, code, group_id, priority from smsnews.topics where id=$id");
		if ($data) {
			$topic = $data[0];
			$data = db_get("select id, groupname from smsnews.topic_groups order by groupname");
			if ($data) {
				$groups = array();
				foreach ($data as $rec) {
					$groups[$rec['id']] = $rec['groupname'];
				}
				$sel_groups = selector('group_id',$groups,$topic['group_id']);
				$raw = tpl('smsnews_topic_edit',array(
					'id' => $id,
					'topic' => htmlspecialchars($topic['topic']),
					'code' => htmlspecialchars($topic['code']),
					'sel_group' => $sel_groups,
					'prio' => $topic['priority']
				));
			}
		}
	}	
	
	webgui_raw($raw);
}

function action_smsnews_topicupdate() {
	$id = $_REQUEST['id']+0;
	$group_id = $_REQUEST['group_id']+0;
	$prio = $_REQUEST['prio']+0;
	$code = $_REQUEST['code'].'';
	$topic = $_REQUEST['topic'];
	$raw = tpl('smsnews_topic_rowerror');
	
	if ($id && $group_id && $code && $topic) {
		db("update smsnews.topics set group_id=$group_id, code='".db_escape($code)."', topic='".db_escape($topic)."' where id=$id");

		smsnews_log('topic updated',"id=$id, code=$code, group_id=$group_id, topic=$topic");

		$raw = tpl('smsnews_topic_row',array(
			'id' => $id,
			'topic' => htmlspecialchars($topic),
			'code' => $code,
			'group_id' => $group_id,
			'priority' => $prio
		));
	}
	
	webgui_raw($raw);
}

function action_smsnews_topicdelete() {
	$id = $_REQUEST['id']+0;
	$raw = 'ERROR';
	
	if ($id) {
		db("delete from smsnews.topics where id=$id");
		smsnews_log('topic deleted',"id=$id");
		$raw ='DELETED';
	}
	
	webgui_raw($raw);
}

function action_smsnews_topicprio() {
	$id = $_REQUEST['id']+0;
	$prio = $_REQUEST['prio']+0;
	$raw = 'ERROR';
	
	if ($id) {
		db("update smsnews.topics set priority=$prio where id=$id");
		smsnews_log('topic updated',"id=$id, prio=$prio");
		$raw = 'OK';
	}
	
	webgui_raw($raw);
}
