<?php

include_once 'simple.webgui.php';
include_once 'app.inc.php';
require_once 'content.api.php';
require_once 'core.inc.php';
require_once 'common.inc.php';

class CMSRequest extends CMS {

	var $qty;
	var $filter;
	var $apps;
	var $operators;
	var $channels;
	var $st;
	var $content_id;

	// Constructor
	function CMSRequest() {

		$this->CMS('request', 'core.messages', array(), 0);

		$this->req['show']='select id from core.messages %where order by id desc %limit %offset';
		$this->req['count']='select count(*) from core.messages %where';
		$this->tasks=array(
			'list'=>'return $this->show();',
			'reply'=>'return $this->reply();',
			'fake'=>'return $this->fake();',
			'send'=>'$status=$this->send();return $this->show();',
		);
		$this->apps=array();
		$this->operators=array();
		$this->channels=array();

		if ($tmp=db_get("select id, name from core.apps")) {
			foreach ($tmp as $rec) {
				$this->apps[$rec['id']]=$rec['name'];
			}
		}

		if ($tmp=db_get("select id,name from core.apps where name like 'chan_%'")) {
			foreach ($tmp as $rec) {
				$this->operators[$rec['name']]=$rec['name'];
				$this->channels[$rec['name']][]=$rec['id'];
			}
		}

		$this->st=array('fail'=>'Failed', 'wait' => 'Progress' ,'ok'=>'OK');
		$this->filter=array(
			'msisdn'=>trim($_REQUEST['fmsisdn']),
			'oper'=>trim($_REQUEST['foper']),
			'date'=>'', // See later
			'code'=>trim($_REQUEST['fcode']),
			'status'=>trim($_REQUEST['fstatus']),
			'lim'=>trim($_REQUEST['lim']),
			'ofs'=>trim($_REQUEST['ofs']),
		);

		if (is_array($_REQUEST['fdate'])) {
			$this->filter['date']=$_REQUEST['fdate']['y'].'-'.$_REQUEST['fdate']['m'].'-'.$_REQUEST['fdate']['d'];
		}

		if (!isset ($_REQUEST['fmsisdn'])) {
			$this->filter['msisdn']=trim($_REQUEST['request-msisdn']);
		}

		if (!isset ($_REQUEST['fstatus'])) {
			$this->filter['status']=trim($_REQUEST['request-status']);
		}

		if (!isset ($_REQUEST['foper'])) {
			$this->filter['oper']=trim($_REQUEST['request-oper']);
		}

		if (!isset ($_REQUEST['fcode'])) {
			$this->filter['code']=trim($_REQUEST['request-code']);
		}

		if (!isset ($_REQUEST['lim'])) {
			$this->filter['lim']=trim($_REQUEST['request-lim']);
		}

		if (!$this->filter['lim']) {
			$this->filter['lim']=50;
		}

		$this->filter['ofs']+=0;

		if (!$this->filter['date']) {
			$this->filter['date']=date('Y-m-d');
		}

		$this->content_id = -1;

		foreach ($this->filter as $key=>$value) {
			setcookie('request-'.$key, $value);
		}
	}

	// SHOW method. 

	function show() {

		global $TPL;

		// Fetch messages by filter
		$list = $this->get_list();

		// Make table of subjects
		$table='';

		if ($list) {

			// Go through fetched messages and create HTML table rows
			foreach ($list as $rec) {
				$msg_id=$rec['id'];
				$hash=array('script'=>$_SERVER['SCRIPT_NAME'], 'id'=>$rec['id'], 'msisdn'=>'n/a', 'time'=>'n/a', 'channel'=>'n/a', 'status'=>'n/a', 'request'=>'n/a', 'premium'=>'n/a');

				if ($rs=db_get("select uuid,src_addr,dst_addr,to_char(date_received,'DD.MM.YYYY HH24:MI') as src_time,
					src_app_id,dst_app_id,msg_status,charging,msg_body,msg_type,
					core.get_mno_name(case when length(src_addr) > length(dst_addr) then src_addr else dst_addr end) as mno
					from core.messages where id=$msg_id")) {

					$hash['uuid']=$rs[0]['uuid'];
					$hash['mno']=$rs[0]['mno'];
					$hash['src_addr']=$rs[0]['src_addr'];
					$hash['dst_addr']=$rs[0]['dst_addr'];
					$hash['time']=$rs[0]['src_time'];
					$hash['msg_body']=$rs[0]['msg_body'];

					$hash['status']=$rs[0]['msg_status'];
					if (($hash['status'] == 'FAILED') and ($rs[0]['src_app_id'] == $rs[0]['dst_app_id'])) {
						$hash['status'] = '<bstyle="color: brown;">FAILED</b> <small>(route loop)</small>';
					}

					if (($hash['status'] == 'FAILED') and (!$rs[0]['dst_app_id'])) {
						$hash['status'] = '<b style="color: brown;">FAILED</b> <small>(no route)</small>';
					}

					$hash['msg_type']=$rs[0]['msg_type'];

					if ($hash['msg_type'] == 'SMS_RAW') {
						$body = json_decode($hash['msg_body']);
						$hash['msg_body'] = $body->{'text'};
						if (!$hash['msg_body']) {
							$hash['msg_body'] = '<b style="color: brown;">RAW SMS PDU</b> (undecoded)'; 
						};
					};


				}

				$table.=template($TPL[$this->prefix.'_row'], $hash);
			}
		}

		// Filter
		$filtertable = template(
			$TPL[$this->prefix.'_filter'],
			array(
				'script' => $_SERVER['SCRIPT_NAME'],
				'fmsisdn' => htmlspecialchars($this->filter['msisdn']),
				'operator'=>selector('foper', $this->operators, $this->filter['oper'], '', translate('any')),
				'status'=>selector('fstatus', $this->st, $this->filter['status'], '', translate('any')),
				'time'=>date_selector('fdate', $this->filter['date']),
				'fcode'=>htmlspecialchars($this->filter['code']),
				'lim'=>$this->filter['lim']
			)
		);

		// Number of messages found
		$htmlqty=template($TPL[$this->prefix.'_qty'], array('qty'=>$this->qty));
		$maintable='';

		if ($list) {
			$maintable=template($TPL[$this->prefix.'_table'], array('script'=>$_SERVER['SCRIPT_NAME'], 'rows'=>$table));
		}

		$navigator='';

		if ($this->qty > $this->filter['lim']) {
			$navigator=template($TPL[$this->prefix.'_nav'], array('nav'=>get_navigator($_SERVER['SCRIPT_NAME'].'?do=request-list', $this->qty, $this->filter['lim'], $this->filter['ofs'])));
		}

		return $filtertable.$htmlqty.$navigator.$maintable.$navigator;
	}


	// Get messages from storage
	function get_list() {

		$filter=$this->filter;
		$warr=array();
		$where='';

		// Nobody wants to see delivery reports
		$warr[]="(msg_type != 'DLR')";

		if ("{$this->filter['msisdn']}" != '') {
			$warr[]="(src_addr like '%".db_escape($filter['msisdn'])."%' or dst_addr like '%".db_escape($filter['msisdn'])."%')";
		}

		if ("{$this->filter['oper']}" != '') {
			$warr[]="(src_app_id in (".join(',', $this->channels[$filter['oper']]).") or dst_app_id in (".join(',', $this->channels[$filter['oper']]).") )";
		}

		if ("{$this->filter['date']}" != '') {
			$warr[]="date_received <= (timestamp '".db_escape($filter['date'])."'+interval '24 hours')";
		}

		if ("{$this->filter['code']}" != '') {
			$warr[]="msg_body ilike '%".db_escape($filter['code'])."%'";
		}

		if ("{$this->filter['status']}" == 'fail') {
			$warr[]="msg_status in ('FAILED', 'REJECTED', 'UNDELIVERABLE','EXPIRED','UNKNOWN')";
		} elseif ("{$this->filter['status']}" == 'wait') {
			$warr[]="msg_status in ('NEW', 'ROUTED', 'SENT')";
		} elseif ("{$this->filter['status']}" == 'ok') {
			$warr[]="msg_status in ('PROCESSED', 'DELIVERED')";
		}

		if ($warr) {
			$where='where '.join(' and ', $warr);
		}

		$offset='offset '.$this->filter['ofs'];
		$limit='limit '.$this->filter['lim'];
		$count=db_get(template($this->req['count'], array('where'=>$where, 'fields'=>$this->fields_str)));

		if ($count) {
			$this->qty=$count[0]['count'];
		} else {
			$this->qty=0;
		}

		$data=db_get(template($this->req['show'], array('where'=>$where, 'limit'=>$limit, 'offset'=>$offset, )));
		return $data;
	}

	function reply() {
		global $TPL, $status;

		if (!($id=$_REQUEST['id']+0)) {
			$status.='; NO TRANSACTION ID!';
			return $this->show();
		}

		$request=trim($_REQUEST['request']);
		$hash=array('script'=>$_SERVER['SCRIPT_NAME'], 'id'=>$id, 'request'=>htmlspecialchars($request));

		if ($rs=db_get("select src_app_id,src_addr,to_char(date_received,'DD.MM.YYYY HH24:MI') as src_time
					 from core.messages where id=$id")) {
			$hash['msisdn']=preg_replace('/(\d{2})(\d{3})(\d+)/', '$1 ($2) $3', $rs[0]['src_addr']);
			$hash['time']=$rs[0]['src_time'];
			$hash['apps']=selector('app_id', $this->apps, $rs[0]['src_app_id']);
		} else {
			$status.='; FAKE TRANSACTION ID!';
			return $this->show();
		}

		return template($TPL[$this->prefix.'_reply'], $hash);
	}

	// Show fake SMS sending form
	function fake() {

		global $TPL, $status;
		$hash=array(
			'script'=>$_SERVER['SCRIPT_NAME'],
			'apps'=>selector('app_id',	$this->apps),
			'request'=>'', 
			'msisdn'=>'380',
			'dst_addr'=>'' 
		);
		return template($TPL[$this->prefix.'_fakeform'], $hash);

	}

	// Send SMS from GUI
	function send() {

		global $DBH;
		$request=htmlspecialchars(trim($_REQUEST['request']));
		$message=array();
		$content=0;

		if ($id=$_REQUEST['id']+0) {

			// Replying an existing message

			if ($rs=db_get("select * from core.messages where id=$id")) {
				$msg=$rs[0];
			} else {
				return translate('bad_message');
			}

			if (!$request) {
				$request=substr(trim($msg['msg_body']), 2);
			}

			$message['msg_type']='SMS_TEXT';
			$message['refer_id']=intval($msg['id']);
			$message['msg_body']=db_escape($request);
			$message['msg_status']='ROUTED';
			$message['retries']=0;
			$message['charging']='fake|gui';
				
			$ret=translate('replied_text');

			$message['src_app_id']=intval($msg['dst_app_id']);
			$message['dst_app_id']=intval($msg['src_app_id']);
			$message['dst_addr']=$msg['src_addr'];
			$message['src_addr']=$msg['dst_addr'];

			$sql="insert into core._messages_queue (".join(',', array_keys($message)).") values ('".join("','", $message)."')";

			if (db_put($sql)) {
				$phone = $msg['src_addr'];
				return $ret.' '.$phone;
			} else {
				return 'ERROR: '.$sql;
			}

		} else {

			// Fake request
			$prefix=trim($_REQUEST['prefix']);
			$msisdn=trim($_REQUEST['msisdn']);
			$app_id=$_REQUEST['app_id']+0;

			$message['msg_type']='SMS_TEXT';
			$message['charging']='fake|gui';
			$message['msg_body']=db_escape($request);
			$message['msg_status']='NEW';
			
			$ret=translate('replied_text');	// text sent to

			$message['src_app_id']=intval($app_id);
			$message['src_addr']=$prefix;
			$message['dst_addr']=$msisdn;

			$sql="insert into core._messages_queue (".join(',', array_keys($message)).") values ('".join("','", $message)."')";

			if (db_put($sql)) {
				return $ret.' '.$msisdn;
			} else {
				return 'ERROR: '.$sql;
			}
		}

	} // function send() 

}
