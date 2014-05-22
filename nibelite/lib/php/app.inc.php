<?php
/*
 * $Id: app.inc.php 159 2008-02-19 11:20:55Z misha $
 */

include_once "common.inc.php";

// Declassified functions: 
// GetChannel returns Application ID by system and service number,
// GetChannels returns App IDs for all channels on this system

function GetChannel($dbh, $system, $sn){
  $app_id = 0;
  $sql = "select app_id from core.apps_conf where tag='channel' and value='{$system}-{$sn}' limit 1";
  if ($res = pg_exec($dbh, $sql)) {
    if ($row = pg_fetch_array($res))
      $app_id = $row['app_id'];
    pg_freeresult($res);
  }
  return $app_id;
}

function GetChannels($dbh, $system){
  $channels = array();
  $sql = "select app_id from core.apps_conf where tag='channel' and value like '{$system}-%'";
  if ($res = pg_exec($dbh, $sql)) {
    while ($row = pg_fetch_array($res))
      $channels[] = $row['app_id'];
    pg_freeresult($res);
  }
  return $channels;
}


// Class: App
// Author: Michael Bochkaryov <misha@inmetex.com.ua>
//
// Provides common functionality for applications:
// - retrieving new messages from queue;
// - putting new messages to queue;
// - checking content availability;
// - logging

class App {

var $id;
var $dbh;
var $name;      // Channel name string for log files, etc.
var $config_table;
var $msg_queue;
var $log_file;  // Log file name.
var $config;



// -----------------------------------------------------------------------------------------
// Function: App (class constructor).
// Parameters: database handler, application identifier
// Initialize all common variables, configuration, etc.

function App($dbh, $app_id, $log_file_name = "applications.log") {

	if (is_int($app_id)) {
  	$sql = "select * from core.apps where id = '$app_id'";
	} else {
		$sql = "select * from core.apps where name = '$app_id'";
	};

	$this->dbh = $dbh;

	if ($res = pg_exec($this->dbh, $sql)) {
		if ($row = pg_fetch_array($res)) {
			$this->id = $row['id'];
			$this->name = $row['name'];
		};
	};

	pg_freeresult($res);

  $this->config_table = "core.apps_conf";
  $this->msg_queue    = "core.messages";
  $this->log_file     = SYS."/var/log/$log_file_name";

  $this->config = $this->GetConfig();
   
} // function App



// -----------------------------------------------------------------------------------------
// Function: GetConfig
// Parameters: none
// Returns hash with parameters.

function GetConfig() {
  $ret_val = array();
  $sql = "select * from " . $this->config_table . " where (app_id = '" . $this->id . "')";
  if ($res = pg_exec($this->dbh, $sql)) {
    while ($row = pg_fetch_array($res)) {
	  if(isset($ret_val[$row['key']]))
	    if(is_array($ret_val[$row['key']]))
	      array_push($ret_val[$row['key']],$row['value']);
	    else
	      $ret_val[$row['key']] = array($ret_val[$row['key']], $row['value']);
	  else
	    $ret_val[$row['key']] = $row['value'];
    }
    pg_freeresult($res);
  }
  return $ret_val;
} // function GetConfig



// -----------------------------------------------------------------------------------------
// Function: GetNewMessages
// Parameters: none
// Returns array of hashes with requests data

function GetNewMessages() {
  global $DEBUG;

  $sql = "select * from " . $this->msg_queue . 
		" where (msg_status = 'ROUTED')
	 	and (dst_app_id = '" . $this->id . "') and (id > 0) LIMIT 1";

  if ($res = pg_exec($this->dbh, $sql)) {
    while ($row = pg_fetch_array($res)) {
      $r[$row['id']] = $row;
      $this->UpdateMessageStatus($row['id'], STATUS_PROCESSING);
    }
    pg_freeresult($res);
  }
  return $r;
} // function GetNewMessages



// -----------------------------------------------------------------------------------------
// Function: PutMessage
// Parameters: message as hash
//		'refer_id'
//		'transaction_id'
//		'msg_type'
//		'msg_body'
//		'msg_status'
// Returns identifier of new message.

function PutMessage($msg) {

  if ($msg['refer_id']) {
    $refer_id = $msg['refer_id'];
  } else {
    $refer_id = 0;
  }

  $transaction_id = $msg['transaction_id'];

  if ($msg['src_app_id'] > 0) {
    $src_app_id = $msg['src_app_id'];
  } else {
    $src_app_id = $this->id;
  }

  $dst_app_id = $msg['dst_app_id'];

  $src_addr = $msg['src_addr'];
  $dst_addr = $msg['dst_addr'];

  $msg_type = $msg['msg_type'];
  $msg_body = pg_escape_string($msg['msg_body']);
  $msg_status = $msg['msg_status'];

  if ($msg['msg_premium']) {
    $msg_premium = $msg['msg_premium'];
  } else {
    $msg_premium = CONTENT_FREE;
  }

  $sql = "insert into " . $this->msg_queue . " (
		refer_id,
		transaction_id,
		src_app_id,
		dst_app_id,
		src_addr,
		dst_addr,
		date_received,
		msg_type,
		msg_body,
		msg_status,
		retries,
		premium )
	values (
		'$refer_id',
		'$transaction_id',
		'$src_app_id',
		'$dst_app_id',
		'$src_addr',
		'$dst_addr',
		current_timestamp,
		'$msg_type',
		'$msg_body',
		'$msg_status',
		0,
		'$msg_premium')";

//  $this->log($sql);

  if ($res = pg_exec($this->dbh, $sql)) {
    $sql = "select id from " . $this->msg_queue . " where oid = '" . pg_getlastoid($res). "'";
//    $this->log($sql);
    if ($res_id = pg_exec($this->dbh, $sql)) {
      if ($row = pg_fetch_array($res_id)) {
	return $row['id']; // Return message identifier for new message
      }
    }
    pg_freeresult($res);
    pg_freeresult($res_id);
  }

  return false;

} // function PutMessage



// -----------------------------------------------------------------------------------------
// Function: UpdateMessageStatus
// Parameters: message identifier, new status
// Returns true if updated successfully

function UpdateMessageStatus($id, $status) {
  if ($status) { $sql_status = ", msg_status = '$status'"; }
  $sql = "update messages set date_processed = current_timestamp$sql_status where (id = '$id')";
  if ($res = pg_exec($this->dbh, $sql)){
    pg_freeresult($res);
    return true;
  }else 
    return false;
} // function UpdateMessageStatus


// -----------------------------------------------------------------------------------------
// Function: GetContent
// Parameters: local content identifier, channel identifier
// Returns true or false depending of content availability

function GetContent($code, $dst_app = -1) {
  if ($dst_app == -1) {
    $dst_app_id = $this->id;
  } else {
    $dst_app_id = $dst_app;
  }
  $sql = "select * from cache_data where ((dst_app_id = '$dst_app_id') or (dst_app_id = 0)) and (code = '$code') and (data != '')";
  if ($res = pg_exec($this->dbh, $sql)) {
    while ($c = pg_fetch_array($res)) {
      $ret_val[$c['key']] = $c['data'];
    }
    return $ret_val;
    pg_freeresult($res);
  }
  return false;
} // function GetContent



// -----------------------------------------------------------------------------------------
// Function: ReadSTDIN
// Reads data from standard input and returns it.

function ReadSTDIN() {
  $ret_val = '';
  while ($str = fread(STDIN, 4096)) { $ret_val .= $str; }
  return $ret_val;
} //function ReadSTDIN




// -----------------------------------------------------------------------------------------
// Function:   Log
// Parameters: string to be written into log file

function Log($string) {
  $now = date("D M j G:i:s T Y");
  error_log("$now [channel " . $this->name . "] $string\n", 3, $this->log_file);
} // function Log



// Function: NewTransactionID

function NewTransactionID() {
  $sql = "select nextval('transaction_id') as tr_id";
  if ($res = pg_exec($this->dbh, $sql)) {
    if ($row = pg_fetch_array($res)) {
      return $row['tr_id'];
    }
    pg_freeresult($res);
  }
} // function NewTransactionID



// Function GetMsg
//
function GetMsg($id) {
  $sql = "select * from messages where id = $id limit 1";
  if ($res = pg_exec($this->dbh, $sql)) {
    if ($row = pg_fetch_array($res)) {
      return $row;
    }
    pg_freeresult($res);
  }
} // function GetMsg



// Function: Close

function Close() {
  // WAAAAAAAAAAAAAARGH!!! pg_close($this->dbh);
} // function Close




// Function: PushSI

function PushSI( $url, $title ) {

  $url = preg_replace('/^\w+:\/\//','',$url);
  $data = 
     'DC'.                  	// Push ID
     '06'.			// Push PDU
     '01AE'.			// Content-Type: application/vnd.wap.sic
     '02056A'.			// version / si / utf-8
     '0045C6'.			// string / si / indication
     '0C03'.bin2hex($url).'00'.	// http:// zstring <url> \0
     '01'.			// Indication
     '03'.bin2hex($title).'00'. // zstring <title> \0
     '0101';			// Indication / SI
  return $this->SmartMessage('0B84',$data);

} // function PushSI


// Function: SmartMessage

function SmartMessage( $port, $hexdata, $oport='0000' ) {

  if(strlen($hexdata)+14<=280){  
                                                // Fit in single message
    return '060504'.$port.'0000'.$hexdata;      // Short UDH
  }else{
                                                // Messages Chain
    $udh = '0B0504'.$port.'0000'.'0003';     	// UDH with concantenation
    $refnum = sprintf('%02X',rand(0,255));      // Chain Reference Number
    $qty = strlen($hexdata) / 256;              // Messages in Chain
    if(strlen($hexdata) % 256) $qty++;             
    if($qty>255) return '';                     // This doesn't fit anyway
    $udh .= sprintf('%02X',$refnum).sprintf('%02X',$qty);
    $result = array();
    for($i=1;$i<=$qty;$i++)                    // Making Messages
      $result[] = $udh.sprintf('%02X',$i).substr($hexdata,256*($i-1),256);
    return $result;
  }

} // function SmartMessage


} // class App

?>
