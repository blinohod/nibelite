<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         System Statistics
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id$

/* init_* functions are called automagically from webgui dispatcher 
 * at init stage.
 * there we are splitting templates
 */
function init_smsnews_stat () {
	global $LANGUAGE;
	read_templates(TEMPLATES.'design.smsnews_stat.'.$LANGUAGE.'.html');
}

function smsnews_count ($table,$where = '') {
	$query = "select count(*) as cnt from $table".( $where != '' ? " where $where" : '' );
	//echo "$query<br>";
	if ($data = db_get($query)) {
		return $data[0]['cnt'];
	} else {
		return false;
	}
}

function action_smsnews_stat () {
	
	$now = 'error';
	if ($data = db_get("select now()")) {
		$now = $data[0]['now'];
	} else {
		return '<h1>Database Error</h1>';
	}
	
	$app_id = 0;
	if ($data = db_get("select id from core.apps where name='app_smsnews'")) {
		$app_id = $data[0]['id'];
	} else {
		return '<h1>Error: core.apps not configured properly</h1>';
	}
	
	return tpl('smsnews_stat', array(
		'now' => substr($now,0,16),
		
		'mo_day' => smsnews_count(
			'core.messages', 
			"dst_app_id=$app_id and date_received<=now() and date_received>now() - interval '1 day'"
		),
		'mo_week' => smsnews_count(
			'core.messages', 
			"dst_app_id=$app_id and date_received<=now() and date_received>now() - interval '1 week'"
		),
		'mo_month' => smsnews_count(
			'core.messages', 
			"dst_app_id=$app_id and date_received<=now() and date_received>now() - interval '1 month'"
		),
		'mt_day' => smsnews_count(
			'core.messages', 
			"src_app_id=$app_id and date_received<=now() and date_received>now() - interval '1 day'"
		),
		'mt_week' => smsnews_count(
			'core.messages', 
			"src_app_id=$app_id and date_received<=now() and date_received>now() - interval '1 week'"
		),
		'mt_month' => smsnews_count(
			'core.messages', 
			"src_app_id=$app_id and date_received<=now() and date_received>now() - interval '1 month'"
		),
		
		'users_total' => smsnews_count('smsnews.subscribers'),
		'users_active' => smsnews_count('smsnews.subscribers', "status = 'ACTIVE'"),
		
		'subs_total' => smsnews_count('smsnews.subscriptions'),
		'subs_active' => smsnews_count('smsnews.subscriptions', "status = 'ACTIVE'"),
		'subs_pending' => smsnews_count('smsnews.subscriptions', "status = 'PENDING'")
		
	));
}

