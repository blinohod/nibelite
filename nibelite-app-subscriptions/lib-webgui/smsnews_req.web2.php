<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         Incoming Messages Admin module
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id$

function action_smsnews_reqlist () {

	$data = db_get("select * from core.messages where dst_addr='6046' order by id desc limit 100");

	$rows = '- NO REQUESTS -';
	if($data) {
		$rows = '';
		foreach ($data as $rec) {
			$rows .= tpl('smsnews_requests_row',array(
				'id' => $rec['id'],
				'msg_body' => $rec['msg_body'],
				'src_addr' => $rec['src_addr'],
				'dst_addr' => $rec['dst_addr'],
				'date_received' => $rec['date_received'],
				'msg_status' => $rec['msg_status'],
			));
		}
	}

	return tpl('smsnews_requests',array(
		'req_limit' => selector('req_limit',array('10'=>10,'50'=>50), $_REQUEST['req_limit']+0),
		'rows' => $rows,
		'f_msisdn' => $_REQUEST['f_msisdn'] .'',
		'f_text' => $_REQUEST['f_text'] .'',
	));

}


