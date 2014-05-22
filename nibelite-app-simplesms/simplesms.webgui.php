<?php

include_once 'simple.webgui.php';

class CMS_Serv extends CMS {


	function CMS_Serv($ad = 1) {

		$this->CMS('simplesms', 'simplesms.services', array(
			'id'=>'0',
			'name'=>'',
			'sn'=>'',
			'active'=>'true',
			'class'=>'LAST',
			'keyword'=>'',
			'descr'=>'',
			'msg_help'=>'',
		), $ad);

		$this->tasks['insert'] = '$status=$this->insert();return $this->show();';
		$this->tasks['update'] = '$status=$this->update();return $this->show();';
		$this->tasks['delete'] = '$status=$this->delete();return $this->show();';
		$this->tasks['topicedit'] = 'return $this->topic_edit();';
		$this->tasks['topicadd'] = '$status=$this->topic_add(); return $this->edit();';
		$this->tasks['topicupdate'] ='$status=$this->topic_update(); return $this->topic_edit();';
		$this->tasks['topicdelete'] ='$status=$this->topic_delete(); return $this->edit();';
		$this->tasks['contentshow'] = 'return $this->content_show();';

		$this->class = array('LAST' => 'LAST', 'RANDOM' => 'RANDOM');

	} // function CMSSimpleSMS ($ad = 0)


	/* Show list of the services
	 *
	 */
	function show() {

		global $TPL, $status;

		$tmp = db_get("select * from simplesms.services order by name");

		$hash = array('rows' => '');

		if ($tmp) {
			foreach ($tmp as $row) {
				// Prepare HTML for one row
				$row['[class]'] = selector('class', $this->class, $row['class']);
				$row['active'] = ($row['active'] == 't' ? 'checked="on"' : '');
				$hash['rows'] .= template($TPL[$this->prefix.'_row'], $row);
			};
		};

		if (!$hash['rows']) {
			// Return HTML for empty row
			$hash['rows'] = template($TPL[$this->prefix.'_empty'], $hash);
		}

		return template($TPL[$this->prefix.'_table'], $hash);

	}

	function edit($load=true) {
		global $TPL, $status;

		$id = $_REQUEST['id']+0;
		$hash = array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'dis' => '',
			'delete' => translate('cms_button_delete')
		);


		if ($load) {
			// Load service descriptor for appropriate ID
			$hash['do'] = $this->prefix."-update";
			$hash['submit'] = translate('cms_button_save');
			$status .= translate('cms_edit');

			// Fetch service descriptor
			$tmp = db_get("select * from simplesms.services where id = $id");
			$data = $tmp[0];
			if ($data) {
				$hash['id'] = $data['id'];
				$hash['descr'] = $data['descr'];
				$hash['sn'] = $data['sn'];
				$hash['name'] = $data['name'];
				$hash['keyword'] = $data['keyword'];
				$hash['msg_help'] = $data['msg_help'];
				$hash["[class]"] = selector('class" style=width:240px', array("LAST"=>'LAST', "RANDOM"=>'RANDOM'), $data['class']);
				if ($data['active'] == 't') { $hash['active'] = 'checked'; };
				$table = '';

				// ==================================================================
				// Fetch topics for the command
				$topics = db_get("select * from simplesms.topics where service_id = ".$data['id']);

				if ($topics) {
					foreach ($topics as $topic) {
						$cmd_row = array ('service_id' => $data['id'],
							'topic_id' => $topic['id'],
							'active' => $topic['active'],
							'name' => $topic['name'],
							'descr' => $topic['descr'],
							'keyword' => $topic['keyword'],
							'template' => $topic['template'],
							'is_default' => $topic['is_default'],
						);

						$table .= template($TPL['simplesms_cmd_row'], $cmd_row);
					}
					$hash["simplesms_cmd_rows"] = $table;
				} else {
					$hash["simplesms_cmd_rows"] = 'No topics found for this service!';
				}
				// ==================================================================

				$addhash = array ('service_id' => $data['id']);
				$hash['simplesms_topic_add_form'] = template($TPL['simplesms_topic_add_form'], $addhash);

			} else {
				print "No data received!<br>";
			} // if ($data)

		} else {

			// Prepare form for adding new record
			$hash['id'] = 0;
			$hash['do'] = $this->prefix."-insert";
			$hash['submit'] = translate('cms_button_add');
			$status .= translate('cms_add');

			$hash['id'] = 0;
			$hash['name'] = "Service name";
			$hash['sn'] = "Short Code";
			$hash['descr'] = "Service desctiption";
			$hash['keyword'] = "Put topic keyword here";
			$hash['msg_help'] = "Help message";
			$hash["[class]"] = selector('class" style=width:240px', array("LAST"=>'LAST', "RANDOM"=>'RANDOM'), 'LAST');
			$hash['active'] = "checked";

			$hash["simplesms_cmd_rows"] = $table;
			$hash['simplesms_topic_add_form'] = ' ';

		}

		return template($TPL[$this->prefix.'_edit'], $hash);

	} // function edit();


	function insert() {
		global $TPL, $status;

		$active = 'false';
		$sn = db_escape($_REQUEST['sn']);
		$name = db_escape($_REQUEST['name']);
		$descr = db_escape($_REQUEST['descr']);
		$keyword = db_escape($_REQUEST['keyword']);
		$msg_help = db_escape($_REQUEST['msg_help']);

		if (isset($_REQUEST['active'])) {
			if ($_REQUEST['active'] == 'on') {
				$active = 'true';
			}
		}

		error_log(print_r($_REQUEST));

		db("insert into simplesms.services (descr, keyword, active, sn, name, msg_help) values ('$descr', '$keyword', '$active', '$sn', '$name', '$msg_help')");

		$status = "New service added: $name";

	}// insert


	function update() {
		global $TPL, $status;

		$active = 'off';
		$id = $_REQUEST['id']; // service to update

		$name = db_escape($_REQUEST['name']); // service name
		$descr = db_escape($_REQUEST['descr']); // description
		$keyword = db_escape($_REQUEST['keyword']);  // keyword regexp
		$msg_help = db_escape($_REQUEST['msg_help']);  // msg_help
		$class = db_escape($_REQUEST['class']); // service class (RANDOM or LAST)
		$sn = db_escape($_REQUEST['sn']); // short code to use

		// Process activity flag
		if (isset($_REQUEST['active'])) {
			if ($_REQUEST['active'] == 'on') {
				$active = 't';
			}
		} else {
			$active = 'f';
		}

		db("update simplesms.services set name='$name', descr='$descr', keyword='$keyword', active='$active', class='$class', sn='$sn', msg_help='$msg_help' where id = $id");

	}


	function topic_add() {

		$service_id = $_REQUEST['service_id'];

		$name = db_escape(trim($_REQUEST['name']));
		$descr = db_escape(trim($_REQUEST['descr']));
		$keyword = db_escape(trim($_REQUEST['keyword']));

		if ($name == '') {
			return 'Do not add empty topics!';
		}

		db("insert into simplesms.topics (service_id, name, descr, keyword, active) values ('$service_id', '$name', '$descr', '$keyword', false)");

		// DIRTY HACK
		$_REQUEST['id'] = $service_id;

	} // topic_add()


	function topic_edit() {
		global $TPL, $status;

		$topic_id = $_REQUEST["id"]+0;
		$service_id = $_REQUEST["service_id"]+0;

		if ($topic_id == 0 || $service_id == 0) {
			return template($TPL['simplesms_topic_edit_noid']);
		}

		$tmp = db_get("select * from simplesms.topics where id = $topic_id");
		$topic = $tmp[0];

		$hash = array ('service_id' => $service_id,
			'topic_id' => $topic_id,
			'sn' => $topic['sn'],
			'name' => $topic['name'],
			'keyword' => $topic['keyword'],
			'is_default' => $topic['is_default'],
			'submit' => translate('cms_save'),

		);
		$table = '';

		return template($TPL["simplesms_topic_edit"], $hash);

	} // function topic_edit


	function topic_update() {

		$keyword = db_escape(trim($_REQUEST['keyword']));
		if ($keyword == '') {
			return "Empty topic. ";
		}
		$topic_id = $_REQUEST["id"]+0;
		$service_id = $_REQUEST["service_id"]+0;
		if ($topic_id == 0 || $service_id == 0) {
			return template($TPL['simplesms_topic_edit_noid']);
		}

		if ($_REQUEST['is_default'] == "on") {
			$Cmd->is_default = 'true';
		} else {
			$Cmd->is_default = 'false';
		}

		if ($Cmd->is_default == 'true') {

		}
		return "Topic saved.";
	}


} // class CMSSimpleSMS  extends CMS


class CMS_Topic extends CMS {


	function CMS_Topic($ad = 1) {

		$this->CMS('simpletopics', 'simplesms.topics', array(
			'id'=>'0',
			'service_id' => 'id=>name from simplesms.services order by name',
			'name'=>'',
			'active'=>'true',
			'keyword'=>'',
			'descr'=>'',
			'template'=>'',
			'is_default'=>'true',
		), $ad, 1);

	} // function CMS_Topic 

}


class CMS_Content extends CMS {


	function CMS_Content($ad = 1) {

		$this->CMS('simplecontent', 'simplesms.content', array(
			'id'=>'0',
			'topic_id' => 'id=>name from simplesms.topics order by name',
			'since'=>'',
			'till'=>'',
			'text'=>'',
		), $ad);

	} // function CMS_Content 

}

class CMS_Ads extends CMS {


	function CMS_Ads($ad = 1) {

		$this->CMS('simpleads', 'simplesms.ads', array(
			'id'=>'0',
			'service_id' => 'id=>name from simplesms.services order by name',
			'msg'=>'',
		), $ad);

	} // function CMS_Ads 

}

?>
