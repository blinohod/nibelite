<?php

include_once '../lib/php/webgui.inc.php'; 

function init_simplesms() {
	global $_PAGE;

	face_control('simplesms');
	read_templates(CONFIG.'/templates/design.simplesms.html');

	$_PAGE = array(
		'page_status' => '',
		'page_title' => translate('simplesms_title'),
		'page_head' => translate('simplesms_head'),
		'page_menu' => tpl('simplesms_menu'),
		'page_main' => '',
		'page_time' => ''
	);

}

function action_default() {
	return tpl('simplesms_default');
}

webgui_init();
webgui_run();

/* -----------------------------------------------------------------
 * SETUP ACTIONS 
 */

function action_simplesms_setup() {
	return tpl('simplesms_serv_list');
}

function action_simplesms_getservices() {
	$page = $_REQUEST['page']+0;
	$active = $_REQUEST['active'].'';

	$cond = 'where active';
	if ($active === 'no')
		$cond = 'where not active';
	elseif ($active === 'any')
		$cond = '';
		
	$data = db_get("select count(*) as cnt from simplesms.services $cond");
	$raw = '';
	if ($data) {
		$total = $data[0]['cnt'];
		if ($total) {
			$limit = 20;
			$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
			$start = $offset + 1;
			$end = $offset + $limit; if ($end > $total) $end = $total; // UI
		
			$nav = tpl('simplesms_serv_nav',array(
				'way_back' => $page>0 ? tpl('simplesms_serv_pageback') : '',
				'way_forward' => $end<$total ? tpl('simplesms_serv_pagenext') : '',
				'start' => $start,
				'end' => $end,
				'total'=> $total
			));
		
			$raw = $total > 7 ? $nav : '';
			
			$data = db_get("select id, name, sn, active, class, keyword, descr, msg_help from simplesms.services $cond order by sn, keyword limit $limit offset $offset");
			if ($data) {
				foreach ($data as $row) {
					$raw .= tpl('simplesms_serv_item',array(
						'id'	=>	$row['id'],
						'item'	=>	tpl('simplesms_serv_itemtable',array(
										'id'		=>	$row['id'],
										'name'		=>	htmlspecialchars($row['name']),
										'sn'		=>	$row['sn'],
										'active'	=>	$row['active'] == 't' 
											? tpl('simplesms_active') 
											: tpl('simplesms_inactive'),
										'class'		=>	$row['class'],
										'keyword'	=>	htmlspecialchars($row['keyword']),
										'descr'		=>	htmlspecialchars($row['descr']),
										'msg_help'	=>	htmlspecialchars($row['msg_help'])
									))
					));
				}
			}
		}
	}

	webgui_raw($raw);
}

function action_simplesms_getsetuptopics () {
	$service_id = $_REQUEST['serv_id'] + 0;
	
	$raw = '';
	
	if ($service_id) {
		$list = '';
		$data = db_get("select id, name, active, keyword, descr, template, is_default, campaign_id from simplesms.topics where service_id=$service_id order by is_default desc, active desc, keyword");
		if ($data) {
			foreach ($data as $row) {
				$list .= tpl('simplesms_topic_item',array(
					'id'		=>	$row['id'],
					'serv_id'	=>	$service_id,
					'name'		=>	htmlspecialchars($row['name']),
					'active'	=>	$row['active'] == 't'
						? tpl('simplesms_active') 
						: tpl('simplesms_inactive'),
					'keyword'	=>	htmlspecialchars($row['keyword']),
					'descr'		=>	htmlspecialchars($row['descr']),
					'template'	=>	htmlspecialchars($row['template']),
					'default'	=>	$row['is_default']=='t' ? 'DEFAULT' : '',
					'campaign'	=> 	'todo'
				));
			}
		}
		
		$raw = tpl('simplesms_topiclist',array(
			'service_id'	=> $service_id,
			'topics'		=> $list
		));
	}
	
	webgui_raw($raw);
}

function action_simplesms_getservedit () {
	$service_id = $_REQUEST['serv_id'] + 0;
	
	$raw = 'Bad request';
	
	if ($service_id) {
		$data = db_get("select id, name, sn, active, class, keyword, descr, msg_help from simplesms.services where id=$service_id");
		if ($data) {
			$serv = array();
			foreach ($data[0] as $name => $value) $serv[$name] = htmlspecialchars($value); 
			
			$serv['s_active'] = $serv['active']=='t' ? 'selected' : '';
			$serv['s_inactive'] = $serv['active']=='t' ? '' : 'selected';
			$serv['s_last'] = $serv['class'] == 'LAST' ? 'selected' : '';
			$serv['s_random'] = $serv['class'] == 'RANDOM' ? 'selected' : '';
			
			$raw = tpl('simplesms_serv_edit',$serv);
		}
	}

	webgui_raw($raw);
}

function action_simplesms_servdelete () {
	$service_id = $_REQUEST['serv_id'] + 0;
	$confirm = trim($_REQUEST['confirm'].'');
	
	$raw = 'Bad request';
	
	if ($service_id) {
		$raw = 'Deletion not confirmed';
		if ($confirm === 'yes') {
			db("delete from simplesms.services where id=$service_id");
			$raw = "DELETED service #$service_id";
		}
	}

	webgui_raw($raw);
}

function action_simplesms_topicdelete () {
	$id = $_REQUEST['id'] + 0;
	$confirm = trim($_REQUEST['confirm'].'');
	
	$raw = 'Bad request';
	
	if ($id) {
		$raw = 'Deletion not confirmed';
		if ($confirm === 'yes') {
			db("delete from simplesms.topics where id=$id");
			$raw = "DELETED topic #$id";
		}
	}

	webgui_raw($raw);
}


function action_simplesms_serveditsave () {
	$id = $_REQUEST['id'] + 0;
	
	$raw = 'Bad request';
	
	if ($id) {
		$fields = array(
			'name'		=> "name='".db_escape(trim($_REQUEST['name'].''))."'",
			'sn'		=> "sn='".db_escape(trim($_REQUEST['sn'].''))."'",
			'active'	=> "active=".($_REQUEST['active']+0 ? "'t'" : "'f'"),
			'class'		=> "class='".db_escape(trim($_REQUEST['class'].''))."'",
			'keyword'	=> "keyword='".db_escape(trim($_REQUEST['keyword'].''))."'",
			'descr'		=> "descr='".db_escape(trim($_REQUEST['descr'].''))."'",
			'msg_help'	=> "msg_help='".db_escape(trim($_REQUEST['msg_help'].''))."'"
		);
		
		$data = db_get(
			"update simplesms.services set ".implode(',',$fields).
			" where id=$id returning ".implode(',',array_keys($fields))
		);

		if ($data) {
			$serv = array();
			foreach ($data[0] as $name => $value) $serv[$name] = htmlspecialchars($value); 
			$serv['active'] = $serv['active']=='t' ? tpl('simplesms_active') : tpl('simplesms_inactive');
			$serv['id'] = $id;
		
			$raw = tpl('simplesms_serv_itemtable',$serv);
		}
	}

	webgui_raw($raw);
}

function action_simplesms_servaddsave () {
	$raw = "Error adding service!";
	
	$fields = array(
		'name'		=> "'".db_escape(trim($_REQUEST['name'].''))."'",
		'sn'		=> "'".db_escape(trim($_REQUEST['sn'].''))."'",
		'active'	=> ($_REQUEST['active']+0 ? "'t'" : "'f'"),
		'class'		=> "'".db_escape(trim($_REQUEST['class'].''))."'",
		'keyword'	=> "'".db_escape(trim($_REQUEST['keyword'].''))."'",
		'descr'		=> "'".db_escape(trim($_REQUEST['descr'].''))."'",
		'msg_help'	=> "'".db_escape(trim($_REQUEST['msg_help'].''))."'"
	);
	
	$data = db_get(
		"insert into simplesms.services (".implode(',',array_keys($fields)).") values (".implode(',',$fields).") returning id"
	);

	if ($data) {
		$id = $data[0]['id'];
		$raw = "Added service #$id";
	}

	webgui_raw($raw);
}


function action_simplesms_gettopicedit () {
	$id = $_REQUEST['id'] + 0;
	
	$raw = 'Bad request';
	
	if ($id) {
		$data = db_get("select t.id, t.service_id, t.name, s.name as service_name, t.active, t.keyword, t.descr, t.template from simplesms.topics t left outer join simplesms.services s on s.id=t.service_id where t.id=$id");
		if ($data) {
			$topic = array();
			foreach ($data[0] as $name => $value) $topic[$name] = htmlspecialchars($value); 
			
			$topic['s_active'] = $topic['active']=='t' ? 'selected' : '';
			$topic['s_inactive'] = $topic['active']=='t' ? '' : 'selected';
			
			$raw = tpl('simplesms_topic_edit',$topic);
		}
	}

	webgui_raw($raw);
}

function action_simplesms_topiceditsave () {
	$id = $_REQUEST['id'] + 0;
	
	$raw = 'Bad request';
	
	if ($id) {
		$fields = array(
			'name'		=> "name='".db_escape(trim($_REQUEST['name'].''))."'",
			'active'	=> "active=".($_REQUEST['active']+0 ? "'t'" : "'f'"),
			'keyword'	=> "keyword='".db_escape(trim($_REQUEST['keyword'].''))."'",
			'descr'		=> "descr='".db_escape(trim($_REQUEST['descr'].''))."'",
			'template'	=> "template='".db_escape(trim($_REQUEST['template'].''))."'"
		);
		
		$data = db_get(
			"update simplesms.topics set ".implode(',',$fields).
			" where id=$id returning id"
		);

		if ($data) {
			$raw = "Saved topic #$id (".trim($_REQUEST['name'].'').")";
		} else {
			$raw = "Error saving topic";
		}
	}

	webgui_raw($raw);
}

function action_simplesms_topicaddsave () {
	$service_id = $_REQUEST['service_id'] + 0;
	
	$raw = 'Bad request';
	
	if ($service_id) {
		$fields = array(
			'service_id'=> $service_id,
			'name'		=> "'".db_escape(trim($_REQUEST['name'].''))."'",
			'active'	=> ($_REQUEST['active']+0 ? "'t'" : "'f'"),
			'keyword'	=> "'".db_escape(trim($_REQUEST['keyword'].''))."'",
			'descr'		=> "'".db_escape(trim($_REQUEST['descr'].''))."'",
			'template'	=> "'".db_escape(trim($_REQUEST['template'].''))."'"
		);
		
		$data = db_get(
			"insert into simplesms.topics (".implode(',',array_keys($fields)).") values (".implode(',',$fields).") returning id"
		);

		if ($data) {
			$raw = "Added topic #$id";
		} else {
			$raw = "Error adding topic";
		}
	}

	webgui_raw($raw);
}


function action_simplesms_gettopicadd () {
	$service_id = $_REQUEST['serv_id'] + 0;
	
	$raw = 'Bad request';
	
	if ($service_id) {
		$data = db_get("select name from simplesms.services where id=$service_id");
		if ($data) {
			$service_name = htmlspecialchars($data[0]['name']);
			$raw = tpl('simplesms_topic_add',array(
				'service_id'	=> $service_id,
				'service_name'	=> $service_name
			));
		}
	}

	webgui_raw($raw);
}


function action_simplesms_topicsetdefault () {
	$id = $_REQUEST['id'] + 0;
	
	$raw = 'Bad request';
	
	if ($id) {
		$data = db_get("select service_id from simplesms.topics where id=$id");
		if ($data) {
			$service_id = $data[0]['service_id'];
			db("update simplesms.topics set is_default=false where service_id=$service_id");
			db("update simplesms.topics set is_default=true where id=$id");
		
			$raw = "Topic #id is set as default for service #service_id";
		}
	}

	webgui_raw($raw);
}

/* -----------------------------------------------------------------
 * CONTENT ACTIONS 
 */

function action_simplesms_content () {
	
	return tpl('simplesms_content');
}

function action_simplesms_contentgettopics () {
	$raw = '';
	
	$services = db_get("select id, sn, keyword, name from simplesms.services order by active desc, sn, keyword");
	if ($services) {
		$list = '';
		
		$topics = array();
		foreach ($services as $serv)
			if (!isset($topics[$serv['id']])) $topics[$serv['id']] = array();
			
		$data = db_get("select id, service_id, keyword, name from simplesms.topics order by service_id, active desc, keyword");
		if ($data)
			foreach ($data as $row)
				$topics[$row['service_id']][] = $row;
		
		foreach ($services as $serv) {
			if ($topics[$serv['id']]) {
				$tl = '';
				foreach ($topics[$serv['id']] as $topic) {
					$tl .= tpl('simplesms_content_menu_topic',array(
						'id'	=>	$topic['id'],
						'title'	=>	'/'.htmlspecialchars($topic['keyword']).'/ - '.htmlspecialchars($topic['name'])
					));
				}
				$list .= tpl('simplesms_content_menu_topics',array(
					'id'	=>	$serv['id'],
					'title'	=>	$serv['sn'].' /'.htmlspecialchars($serv['keyword']).'/ - '.htmlspecialchars($serv['name']),
					'topics'	=>	$tl
				));
			} else {
				$list .= tpl('simplesms_content_menu_empty',array(
					'id'	=>	$serv['id'],
					'title'	=>	$serv['sn'].' /'.htmlspecialchars($serv['keyword']).'/ - '.htmlspecialchars($serv['name'])
				));
			}
		}
		
		$raw = tpl('simplesms_content_menu',array(
			'list' => $list
		));
	}
	
	webgui_raw($raw);
}


function action_simplesms_contenttopic () {
	$raw = '';
	$topic_id = $_REQUEST['id']+0;
	
	if ($topic_id) {
		$subst = array();
		
		$data = db_get("select id as topic_id, service_id as serv_id, keyword as topic_keyword, name as topic_name, descr as topic_descr, active as topic_active from simplesms.topics where id=$topic_id");
		if ($data) {
			foreach ($data[0] as $field => $val) 
				$subst[$field] = htmlspecialchars($val);
			$data = db_get("select sn as serv_sn, keyword as serv_keyword, name as serv_name, descr as serv_descr, active as serv_active from simplesms.services where id=".$subst['serv_id']);
			if ($data) 
				foreach ($data[0] as $field => $val) 
					$subst[$field] = htmlspecialchars($val);
			$subst['topic_active'] = $subst['topic_active']=='t' ? 'yes' : 'no';
			$subst['serv_active'] = $subst['serv_active']=='t' ? 'yes' : 'no';
			
			$raw = tpl('simplesms_content_topic',$subst);
		}
	}

	webgui_raw($raw);
}

function action_simplesms_contentlist () {
	$raw = '';
	$topic_id = $_REQUEST['topic_id']+0;
	$service_id = $_REQUEST['serv_id']+0;
	$page = $_REQUEST['page']+0;

	if ($topic_id && $service_id) {
		$data = db_get("select count(*) as cnt from simplesms.content where topic_id=$topic_id");
		$raw = '';
		if ($data) {
			$total = $data[0]['cnt'];
			if ($total) {
				$limit = 50;
				$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
				$start = $offset + 1;
				$end = $offset + $limit; if ($end > $total) $end = $total; // UI
			
				$back = $page>0 ? tpl('simplesms_content_back') : '';
				$next = $end<$total ? tpl('simplesms_content_more') : '';
			
				$data = db_get(
					"select".
						" c.id, c.text, c.since::date, c.till::date, count(h.id) as orders".
					" from simplesms.content c".
						" left outer join simplesms.history h on c.id=h.content_id".
					" where c.topic_id = $topic_id".
					" group by c.id, c.text, c.since, c.till".
					" order by c.since desc, c.till desc".
					" limit $limit offset $offset"
				);
				
				if ($data) {
					$list = '';
					foreach ($data as $row) {
						$row['text'] = htmlspecialchars($row['text']);
						$list .= tpl('simplesms_content_messages_item',$row);
					}
					
					$raw = tpl('simplesms_content_messages',array(
						'total'		=> $total,
						'start'		=> $start,
						'end'		=> $end,
						'back'		=> $back,
						'more'		=> $next,
						'messages'	=> $list
					));
				}

			} else {
				$raw = tpl('simplesms_content_empty');
			}
		}
	}
	
	webgui_raw($raw);
}

function action_simplesms_contentadd () {
	$raw = 'Bad request!';
	
	$serv_id = $_REQUEST['serv_id']+0;
	$topic_id = $_REQUEST['topic_id']+0;
	
	if ($serv_id && $topic_id) {
		$raw = tpl('simplesms_content_edit',array(
			'serv_id'	=> $serv_id,
			'topic_id'	=> $topic_id,
			'content_id'=> 0,
			'formtitle'	=> tpl('simplesms_formtitle_add'),
			'since'		=> date('d.m.Y'),
			'till'		=> date('d.m.Y',time()+3600*24*30),
			'text'		=> '',
			'delete'	=> ''
		));
	}
	
	webgui_raw($raw);
}

function action_simplesms_contentedit () {
	$raw = 'Bad request!';
	
	$content_id = $_REQUEST['content_id']+0;
	$serv_id = $_REQUEST['serv_id']+0;
	$topic_id = $_REQUEST['topic_id']+0;
	
	if ($serv_id && $topic_id && $content_id) {
		$data = db_get("select text,since::date,till::date from simplesms.content where id=$content_id and topic_id=$topic_id");
		if ($data) {
			$raw = tpl('simplesms_content_edit',array(
				'serv_id'	=> $serv_id,
				'topic_id'	=> $topic_id,
				'content_id'=> $content_id,
				'formtitle'	=> tpl('simplesms_formtitle_edit',array('content_id'=>$content_id)),
				'since'		=> $data[0]['since'],
				'till'		=> $data[0]['till'],
				'text'		=> htmlspecialchars($data[0]['text']),
				'delete'	=> tpl('simplesms_content_delete')
			));
		}
	}
	
	webgui_raw($raw);
}


function action_simplesms_contentsave () {
	$raw = 'Bad request!';
	
	$content_id = $_REQUEST['content_id']+0;
	$topic_id = $_REQUEST['topic_id']+0;
	$serv_id = $_REQUEST['serv_id']+0;
	
	$text = db_escape(trim($_REQUEST['text'].''));
	$since = db_escape(trim($_REQUEST['since'].''));
	$till = db_escape(trim($_REQUEST['till'].''));
	
	$delete = $_REQUEST['delete']+0;
	
	if ($serv_id && $topic_id) {	// Just validity check
		if ($content_id) {
			if ($delete) {
				db("delete from simplesms.content where id=$content_id");
				$raw = 'Deleted Content message #'.$content_id;
			} else {
				$data = db_get("update simplesms.content set text='$text',since='$since',till='$till' where id=$content_id returning id");
				if ($data) {
					$raw = 'Updated Content message #'.$data[0]['id'];
				} else {
					$raw = 'Error updating Content message!';
				}	
			}
		} else {
			$data = db_get("insert into simplesms.content (topic_id,text,since,till) values ($topic_id,'$text','$since','$till') returning id");
			if ($data) {
				$raw = 'Added Content message #'.$data[0]['id'];
			} else {
				$raw = 'Error adding Content message!';
			}
		}
	}
	
	webgui_raw($raw);
}


/* -----------------------------------------------------------------
 * ADVERTISING MANAGEMENT ACTIONS 
 */

function action_simplesms_ads () {
	return tpl('simplesms_ads');
}

function action_simplesms_adshowcampaigns () {
	$raw = '';
	
	$page = $_REQUEST['page']+0;
	
	$data = db_get("select count(*) as cnt from simplesms.campaigns");
	$raw = 'database error';
	if ($data) {
		$total = $data[0]['cnt'];
		if ($total) {
			$limit = 20;
			$offset = $page * $limit; if($offset >= $total) $offset = $total - $total % $limit;
			$start = $offset + 1;
			$end = $offset + $limit; if ($end > $total) $end = $total; // UI
		
			$back = $page>0 ? tpl('ad_camp_back') : '';
			$next = $end<$total ? tpl('ad_camp_more') : '';
		
			// Now exposing a bunch of SELECTs. :(
			// Need to show up to $limit CAMPAIGNS with unlimited count of ads.
		
			$data = db_get(
				"select".
					" id, name, active".
				" from simplesms.campaigns".
				" order by active desc, name".
				" limit $limit offset $offset"
			);
			
			if ($data) {
				$list = '';
				
				foreach ($data as $row) {
					// id, name, active
					$camp_id = $row['id'];
					$row['name'] = htmlspecialchars($row['name']);
					$row['active'] = tpl( 
						$row['active'] ? 'ad_camp_active' : 'ad_camp_inactive',
						array('id'=>$camp_id)
					);
					$row['ads'] = '';
					
					$ads = db_get(
						"select id,msg".
						" from simplesms.ads".
						" where campaign_id=$camp_id".
						" order by msg"
					);
					if ($ads) {
						foreach ($ads as $ad) {
							$row['ads'] .= tpl('ad_camp_ad',array(
								'id' => $ad['id'],
								'name' => htmlspecialchars($ad['msg'])
							));
						}
					}
					
					$list .= tpl('ad_camp_campaign', array(
						'id' => $camp_id,
						'campdata' => tpl('ad_camp_campdata',$row)
					));
				}
				
				$raw = tpl('ad_camp_list',array(
					'total'		=> $total,
					'start'		=> $start,
					'end'		=> $end,
					'back'		=> $back,
					'more'		=> $next,
					'list'		=> $list
				));
			}
		} else {
			$raw = tpl('ad_camp_list',array(
				'total'		=> 0,
				'start'		=> 0,
				'end'		=> 0,
				'back'		=> '',
				'more'		=> '',
				'list'		=> tpl('ad_camp_empty')
			));
		}
	}
	
	webgui_raw($raw);
}

function action_simplesms_adcampinsert () {
	$raw = 'DONE';
	
	$name = db_escape(trim($_REQUEST['name'].''));
	if ($name !== '') {
		$data = db_get("insert into simplesms.campaigns (name,active) values ('$name',1) returning id");
		if ($data) {
			$raw = "Added Campaign #".$data[0]['id'];
		}
	}
	
	webgui_raw($raw);
}

function action_simplesms_adcampupdate () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$name = db_escape(trim($_REQUEST['name'].''));
	
	if ($id && $name!=='') {
		$raw = 'Database Error';
		$data = db_get("update simplesms.campaigns set name='$name' where id=$id returning name");
		if ($data) {
			$raw = htmlspecialchars($data[0]['name']);
		}
	}
	
	webgui_raw($raw);
}

function action_simplesms_adcampdelete () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	if ($id) {
		db("delete from simplesms.ads where campaign_id=$id");
		db("update simplesms.topics set campaign_id=0 where campaign_id=$id");
		db("delete from simplesms.campaigns where id=$id");
	}
	
	webgui_raw($raw);
}

function action_simplesms_adcampactive () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$active = $_REQUEST['active']+0 ? 1 : 0;
	
	if ($id) {
		$raw = 'Database Error';
		$data = db_get("update simplesms.campaigns set active=$active where id=$id returning active");
		if ($data) {
			$raw = tpl( 
				$data[0]['active'] ? 'ad_camp_active' : 'ad_camp_inactive',
				array('id'=>$id)
			);
		}
	}
	
	webgui_raw($raw);
}


function action_simplesms_adadinsert () {
	$raw = 'DONE';
	
	$camp_id = $_REQUEST['camp_id']+0;
	$name = db_escape(trim($_REQUEST['name'].''));
	
	if ($camp_id && $name !== '') {
		$data = db_get("insert into simplesms.ads (msg,campaign_id) values ('$name',$camp_id) returning id,msg");
		if ($data) {
			$raw = tpl('ad_camp_ad',array(
				'id' => $data[0]['id'],
				'name' => htmlspecialchars($data[0]['msg'])
			));
		}
	}
	
	webgui_raw($raw);
}

function action_simplesms_adadupdate () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$name = db_escape(trim($_REQUEST['name'].''));
	
	if ($id && $name!=='') {
		$raw = 'Database Error';
		$data = db_get("update simplesms.ads set msg='$name' where id=$id returning msg");
		if ($data) {
			$raw = htmlspecialchars($data[0]['msg']);
		}
	}
	
	webgui_raw($raw);
}

function action_simplesms_adaddelete () {
	$raw = 'DONE';
	
	$id = $_REQUEST['id']+0;
	if ($id) {
		db("delete from simplesms.ads where id=$id");
	}
	
	webgui_raw($raw);
}

function action_simplesms_adshowmappings () {
	$raw = '';
	
	$data = db_get("select id,name from simplesms.campaigns where active=1 order by name");
	if ($data) {
		$camp = array();
		$camp['0'] = '* * * no advertisements * * *';
		foreach ($data as $row)
			$camp[$row['id']] = htmlspecialchars($row['name']);
			
		$services = db_get("select id, sn, name from simplesms.services where active order by sn, name");
		if ($services) {
			$list = '';
			
			$topics = array();
			foreach ($services as $serv)
				if (!isset($topics[$serv['id']])) $topics[$serv['id']] = array();
				
			$data = db_get("select id, service_id, name, campaign_id from simplesms.topics where active order by service_id, name");
			if ($data)
				foreach ($data as $row)
					$topics[$row['service_id']][] = $row;
			
			foreach ($services as $serv) {
				$tl = '';
				if ($topics[$serv['id']]) {
					foreach ($topics[$serv['id']] as $topic) {
						$sel = '';
						foreach ($camp as $camp_id => $camp_name)
							$sel .= '<option value="'.$camp_id.'"'.($topic['campaign_id']==$camp_id ? ' selected="1"' : '').'>'.$camp_name.'</option>';

						$tl .= tpl('ad_map_topic',array(
							'id'		=>	$topic['id'],
							'name'		=>	htmlspecialchars($topic['name']),
							'options'	=>	$sel
						));
					}
				}
				
				$sel = '<option value="NOTHING" selected="1">* * * change for all topics:</option>';
					foreach ($camp as $camp_id => $camp_name)
						$sel .= '<option value="'.$camp_id.'">'.$camp_name.'</option>';
				
				$list .= tpl('ad_map_serv',array(
					'id'	=>	$serv['id'],
					'sn'	=>	$serv['sn'],
					'name'	=>	htmlspecialchars($serv['name']),
					'topics'	=>	$tl,
					'options'	=>	$sel
				));
			}
			
			$raw = tpl('ad_map_list',array(
				'list' => $list
			));
		}

	}
	
	webgui_raw($raw);
}

function action_simplesms_admaptopic () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$camp_id = $_REQUEST['camp_id']+0;
	
	if ($id && $camp_id) {
		$raw = 'DONE';
		db("update simplesms.topics set campaign_id=$camp_id where id=$id");
	}
	
	webgui_raw($raw);
}

function action_simplesms_admapserv () {
	$raw = 'Bad Request';
	
	$id = $_REQUEST['id']+0;
	$camp_id = $_REQUEST['camp_id']+0;
	
	if ($id && $camp_id) {
		$raw = 'DONE';
		db("update simplesms.topics set campaign_id=$camp_id where service_id=$id");
	}
	
	webgui_raw($raw);
}
