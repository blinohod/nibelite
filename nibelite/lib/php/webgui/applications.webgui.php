<?php

include_once 'simple.webgui.php';

class CMSApplications extends CMS {

	// Constructor
	function CMSApplications() {

		global $LANGUAGE;
		$this->CMS('apps', 'core.apps', array('id'=>'0', 'name'=>'', 'active'=>'true', 'descr'=>''), 1);

		$this->tasks['update'] = '$status=$this->update();return $this->edit();';

	} // function CMSApplications()

	// ===============================================================================================
	// Edit application data (descriptor and settings)
	function edit ($load=true) {

		global $TPL, $status;
		$id = $_REQUEST['id']+0; // core.apps.id

		$hash = array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'dis' => '',
			'config' => '',
			'do' => $this->prefix.'-insert'
		);

		foreach($this->fields as $field => $descr) {
			$hash[$field] = '';
		};

		$hash['id'] = 0; // Oh. Of course, this is bad. I know.
		$localstatus = translate('cms_add');

		// Load application configuration
		if ($load) {

			if ($data = db_get(template($this->req['edit'],array(
				'table'=>$this->table,
				'fields'=>$this->fields_str,
				'id'=>$id
				)))) {

				// Fill hash for creating HTML output
				foreach($data[0] as $field=>$value) {
					if (($field == 'active') and ($value == 't')) {
						$value = ' checked';
					}
					$hash[$field] = htmlspecialchars($value);
				}

				$localstatus = translate('cms_edit');
				$hash['do'] = $this->prefix.'-update';
				$hash['dis'] = ' readonly';
	
				$rows = '';

				if ($data = db_get("select id, tag, value from core.apps_conf where app_id=$id order by tag, value")) {

					foreach($data as $row) {

						$key = $row['tag'];
						$descr = '';

						// Add configuration parameter description
						if(translated('ac_'.$key)) {

							$descr = template($TPL[$this->prefix.'_descr'],array(
								'script' => $_SERVER['SCRIPT_NAME'],
								'tag' => $key,
								'descr' => translate('ac_'.$key)
							));

						} else {

							$descr = template($TPL[$this->prefix.'_nodescr'],array(
								'script' => $_SERVER['SCRIPT_NAME'],
								'key' => $key,
							));

						};

						// Create configuration row
						$rows .= template($TPL[$this->prefix.'_confrow'],array(
							'i' => $row['id'],
							'key' => htmlspecialchars($key),
							'value' => htmlspecialchars($row['value']),
							'descr' => $descr
						));

				};

			};

			$hash['config'] = template($TPL[$this->prefix.'_editconf'],array(
				'script' => $_SERVER['SCRIPT_NAME'],
				'rows'   => $rows,
				'id'     => $id
			));
		};

	};

		$status .= $localstatus;
		return template($TPL[$this->prefix.'_edit'],$hash);

	}


	// Update application configuration
	function update() {

		$form = array();

		foreach($this->fields as $field=>$descr) {
			$form[$field] = $_REQUEST[$field].'';
		}

		if(!db_get(template($this->req['check'],array(
			'table'=>$this->table,
			'id'=>$form['id']+0
			)))) {
				return translate('cms_noid');
		};

		$fields = array();
		foreach ($form as $field=>$value) {
			if ( $field == 'active') {
				if ($value) {
					$value = 'true';
				} else {
					$value = 'false';
				};
			};
			$fields[] = "{$field}='".db_escape(trim($value))."'";
		};

		// echo '<pre>';print_r($fields); echo '</pre>';

		if ($_REQUEST['copy']) {
			
			// Copy application configuration with new ID
			if($data = db_get(template($this->req['maxid'],array('table'=>$this->table)))) {
				$form['id'] = $data[0]['max_id']+1;
			} else {
				return translate('cms_badid').' - '.$data[0]['title'];
			};

			$fields1 = array();
			$values1 = array();

			foreach($form as $field=>$value) {
				if ( $field == 'active') {
					if ($value) {
						$value = 'true';
					} else {
						$value = 'false';
					};
				};
				
				$fields1[] = $field;
				$values1[] = "'".db_escape(trim($value))."'";

			};

			if(db(template($this->req['insert'],array(
				'table'   => $this->table,
				'fields'  => join(',',$fields1),
				'values'  => join(',',$values1)
			)))) {
				$status .= translate('cms_added').' : ';
				$_REQUEST['id'] = $form['id'];
				$keys = $_REQUEST['key'];
				$values = $_REQUEST['value'];

				foreach($keys as $cid=>$key) {
					if ($keys[$cid]) {
						db("insert into core.apps_conf (app_id,tag,value) values ({$form['id']},'".
							db_escape($keys[$cid])."','".db_escape($values[$cid])."')");
					};
				};

				$status .= translate('config_copied').' : ';

			} else {
				$status .= translate('config_not_copied').' : ';
			};

			return $status;

		};

		if(db(template($this->req['update'],array(
			'table'   => $this->table,
			'id'      => $form['id']+0,
			'set'     => join(',',$fields)
		)))) {

			$changed = $_REQUEST['changed'];
			$keys = $_REQUEST['key'];
			$values = $_REQUEST['value'];

			foreach ($changed as $cid=>$cflag) {

				if ($cflag) {

					if ($cid) {

						if (strlen(trim($keys[$cid]))) {

							db("update core.apps_conf set "
								. "tag=btrim('".db_escape($keys[$cid])."'), "
							  . "value=btrim('".db_escape($values[$cid])."') "
								. "where id=".db_escape($cid));
							$status .= "upd:$cid ";

						} else {

							db("delete from core.apps_conf where id=".db_escape($cid));
							$status .= "del:$cid ";

						};

					} else {

						db("insert into core.apps_conf (app_id,tag,value) values (
							{$form['id']},
							btrim('".db_escape($keys[$cid])."'),
					 		btrim('".db_escape($values[$cid])."')
						)");
						$status .= "new:{$keys[$cid]} ";

					};
				
				};

			};

			$status .= translate('cms_updated').' : ';

		} else {
			$status .= translate('cms_upderror').' : ';
		}

		return $status;

	} // function update() 

}

?>
