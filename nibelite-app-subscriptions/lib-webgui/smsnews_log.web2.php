<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         Subscribers / Subscriptions Admin module
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id$

function init_smsnews_log() {
	global $LANGUAGE;
	read_templates(TEMPLATES.'design.smsnews_log.'.$LANGUAGE.'.html');
}

function action_smsnews_events () {

	$date = '';
	if ($data = db_get("select now()::date")) {
		$date = $data[0]['now'];
	}

	return tpl('smsnews_log',array(
		'date' => $date
	));
}

function action_smsnews_logresult () {
	$date = trim($_REQUEST['date'].'');
	if (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/',$date)) {
		$date = '';
	}
	
	$date = $date === '' ? "now()" : "'".$date."'";
	
	$raw = tpl('smsnews_log_empty');
	
	$data = db_get(
		"select ".
			"log.id, log.created, log.subscriber_id, log.message, log.level, log.event, ".
			"users.msisdn, users.name ".
		"from ".
			"smsnews.log as log left outer join smsnews.subscribers as users ".
			"on users.id=log.subscriber_id ".
		"where log.created::date=$date ".
		"order by ".
			"log.created desc"
	);
			
	if ($data) {
		$total = count($data);
		$events = '';
		
		foreach ($data as $rec) {
			if ( ($rec['msisdn'] != '') || ($rec['name'] != '') ) {
				$subscriber = $rec['msisdn'].' '.$rec['name'];
			} else {
				$subscriber = $rec['subscriber_id'];
			}
			
			$events .= tpl('smsnews_log_event',array(
				'id' => $rec['id'],
				'level' => $rec['level'],
				'created' => substr($rec['created'],0,19),
				'subscriber' => $subscriber,
				'event' => htmlspecialchars($rec['event']),
				'message' => htmlspecialchars($rec['message'])
			));
		}
				
		$raw = tpl('smsnews_log_events', array(
			'events' => $events,
			'date' => $date,
			'total' => $total
		));
	}
	
	webgui_raw($raw);
} 
