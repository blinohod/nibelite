<?php   //      PROJECT:        Nibelung 3

require_once 'simple.webgui.php'; 

class CMSCards extends CMS {

	function CMSCards() {
		$this->CMS('cards','cards',array(),0); // just for fun
		$this->tasks = array(
			'show' => 'return $this->show();'
		);
	}

	// SHOW method. 
	function show () {

		global $TPL;

		$msg_id = $_REQUEST['id']+0; # Message ID to show
		$hash = array(); # Output hash

		if(!$msg_id) {
			return '<h1>Message ID not provided!</h1>';
		};

		// Dealing with message
		$msg = db_get("select * from core.messages where id=$msg_id");
		if(!$msg) {
			return '<h1>No message with ID '.$msg_id.' found!</h1>';
		};

		$hash['src_addr'] = trim($msg[0]['src_addr']);
		$hash['dst_addr'] = trim($msg[0]['dst_addr']);

		if ($msg[0]['msg_type'] == 'SMS_TEXT') {
			$hash['msg_body'] = htmlspecialchars($msg[0]['msg_body']);
		} elseif ($msg[0]['msg_type'] == 'DLR') {
			$hash['msg_body'] = '[DLR] ' . htmlspecialchars($msg[0]['msg_body']);
		}

		$src_app_id  = $msg[0]['src_app_id']+0;
		$dst_app_id  = $msg[0]['dst_app_id']+0;

		// Answered message
		if ($data = db_get("SELECT msg_body FROM messages WHERE transaction_id=$trans_id AND dst_app_id=$app_id"))
			$hash['answer'] = $data[0]['msg_body'];
		else
			$hash['answer'] = 'n/a';
	
		// Application...
		if($data = db_get("select name from apps where id=$app_id"))
			$hash['channel'] = "[$app_id] ".$data[0]['name'];
		else
			$hash['channel'] = 'n/a';

		// Render HTML template
		return template($TPL[$this->prefix.'_show'], $hash);

	}

}

?>
