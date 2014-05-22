<?php   //      PROJECT:        Nibelung 3
        //      MODULE:         Simple CMS class
        //      $Id: simple.webgui.php 159 2008-02-19 11:20:55Z misha $

read_templates(TEMPLATES.'design.main.'.$LANGUAGE.'.html'); # read once if were using GUI

// Simple CMS class. Can handle one table with some items.
// Table MUST HAVE column named 'id' as a unique key.

class CMS {
	// Table name
	var $table;
	// Templates/Messages prefix
	var $prefix;
	// Fields definition as associative array: 'field_name'=>'nickname'
	var $fields;
	var $fields_str;  // comma-separated
	// Allow Delete flag
	var $allow_delete;
	// Filter rows by first field
	var $filtering;
	var $filter;
	var $keyfield;
	// Request templates
	var $req;
	// CMS tasks
	var $tasks;

	// Constructor
	function CMS($pre,$tab,$fld,$ad=0,$filtering=0) {

		global $LANGUAGE;

		$this->prefix = $pre;
		$this->table = $tab;
		$this->filtering = $filtering;

		$this->fields = array();
		foreach($fld as $field=>$descr){
			if(!isset($this->keyfield))
				$this->keyfield = $field;
			$this->fields[$field] = $descr; // kinda initializing anyway

			// loading reference values into fields[$field][] (if $descr =~ "mykey => myvalue from myothertable")
			if(preg_match('/^(\w+)\s*\=\>\s*(\w+)\s+(from.*)$/',$descr,$matches)){
				$refKeyName = $matches[1];
				$refValName = $matches[2];
				$refFrom = $matches[3];
				if($data = db_get("select $refKeyName,$refValName $refFrom")){
					$this->fields[$field] = array();
					foreach($data as $rec)
						$this->fields[$field][$rec[$refKeyName]] = $rec[$refValName];
				}
			}
		}
		
		if($this->filtering)
			if(isset($_REQUEST['filter'])){
				$this->filter = $_REQUEST['filter'];
				if(!isset($_REQUEST[$this->keyfield]))
					$_REQUEST[$this->keyfield] = $this->filter;
			}elseif(isset($_REQUEST[$this->keyfield]))
				$this->filter = $_REQUEST[$this->keyfield];

		$this->fields_str = join(',',array_keys($this->fields));
		$this->allow_delete = $ad;
		$this->req = array(
			'show' 		=> 'select %fields from %table order by %fields',
			'showfilter'=> 'select %fields from %table where %keyfield=\'%filter\' order by %fields',
			'edit' 		=> 'select %fields from %table where id=\'%id\'',
			'nextid' 	=> 'select nextval(\'%table_id_seq\') as next_id',
			'maxid' 	=> 'select max(id) as max_id from %table',
			'check' 	=> 'select id from %table where id=\'%id\'',
			'insert' 	=> 'insert into %table(%fields) values (%values)',
			'update' 	=> 'update %table set %set where id=\'%id\'',
			'delete' 	=> 'delete from %table where id=\'%id\''
		);
		$this->tasks = array(
			'list' 		=> 'return $this->show();',
			'add' 		=> 'return $this->edit(false);',
			'edit' 		=> 'return $this->edit();',
			'insert' 	=> '$this->redir("list",$this->insert(),array("filter"=>$this->filter));',
			'update' 	=> '$this->redir("list",$this->update(),array("filter"=>$this->filter));',
			'delete' 	=> '$this->redir("list",$this->delete(),array("filter"=>$this->filter));'
		);
		read_templates(TEMPLATES.'design.'.$this->prefix.'.'.$LANGUAGE.'.html');
	}
  
  // HANDLE method. This is an CMS tasks SUB-dispatcher.
  // Our task-handles format is: "do=<cms prefix>&task=<cms task>"
  // At example, "insert vendor" handle is: "do=vendor&task=insert"
  
	function handle () {
		$task = trim($_REQUEST['task'].'');
		
		if(isset($_REQUEST['status'])) webgui_add('status', ' : '.$_REQUEST['status']);
		
		if ($task == '') 
			$task = 'list';
		if (isset($this->tasks[$task])) {
			return eval($this->tasks[$task]);
		} else 
			return '';
	}
 
  // REDIRECT method. Used to prevent re-posting some data when reloading page
 
	function redir ($task,$status='',$params=array(),$anchor='') {
		$query = "Location: {$_SERVER['SCRIPT_NAME']}?do={$this->prefix}&task={$action}&status=".urlencode($st);
		if ($params)
			foreach($params as $k=>$v)
				$query .= "&{$k}=".urlencode($v);
		if ($anchor)
			$query .= "#$anchor";
		header($query);
		exit;
	}
 
  	// SHOW method. 
  
	function show () {
		$filterlist = '';
		if($this->filtering){
			if($data = db_get(template("select distinct %field from %table order by %field",array(
				'table' => $this->table,
				'field' => $this->keyfield
			)))){
				if(!isset($this->filter))
					$this->filter = $data[0][$this->keyfield]; // Use first value found if the filter not set
				foreach($data as $rec){
					if($rec[$this->keyfield] === $this->filter){
						$head = ' <b>';
						$tail = '</b> ';
					}else{
						$head = " [<a href=\"{$_SERVER['SCRIPT_NAME']}?do={$this->prefix}&task=list&filter=".urlencode($rec[$this->keyfield])."\">";
						$tail = '</a>] ';
					}
					if(is_array($this->fields[$this->keyfield]) && isset($this->fields[$this->keyfield][$rec[$this->keyfield]]))
						$middle = $this->fields[$this->keyfield][$rec[$this->keyfield]];
					else
						$middle = $rec[$this->keyfield];
					$filterlist .= $head . $middle . $tail;
				}
			}
		}
    
		if($filterlist)
			$data = db_get(template($this->req['showfilter'],array(
				'table'		=> $this->table,
				'fields'	=> $this->fields_str,
				'keyfield'	=> $this->keyfield,
				'filter'	=> db_escape($this->filter)
			)));
		else {
			$data = db_get(template($this->req['show'],array(
				'table'=>$this->table,
				'fields'=>$this->fields_str
			)));
		}
			
		$table = '';
		if($data)
			foreach($data as $rec){
				$hash = array('script' => $_SERVER['SCRIPT_NAME']);
				foreach($rec as $field=>$value)
					$hash[$field] = htmlspecialchars($value);
				foreach($this->fields as $field=>$contents)
					if(is_array($contents)){
						$hash["[{$field}]"] = selector($field.'" style="width:240px',
							$contents,$rec[$field],
							0,translate('none'));
						$hash["({$field})"] = isset($contents[$rec[$field]]) 
							? $contents[$rec[$field]] 
							: 'NO DATA '.$rec[$field];
					}
				$table .= tpl($this->prefix.'_row',$hash);
			}
		else
			$table = tpl(
				$this->prefix.'_empty',
				array('script'=>$_SERVER['SCRIPT_NAME'])
			);
		
		$hash = array(
			'rows'		=> $table,
			'filter'	=> $filterlist,
			'script'	=> $_SERVER['SCRIPT_NAME']
		);
		foreach($this->fields as $field=>$contents)
		if(is_array($contents)){
			$hash["[{$field}]"] = selector($field.'" style="width:240px',$contents,$_REQUEST[$field]);
			$hash["({$field})"] = isset($contents[$_REQUEST[$field]]) ? $contents[$_REQUEST[$field]] : 'NO DATA '.$_REQUEST[$field];
		}
	
		return tpl($this->prefix.'_table',$hash);
	}
  
  // ADD/EDIT method.
  
	function edit ($load=true) {
		$id = $_REQUEST['id']+0;
		$hash = array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'dis' => '',
			'do' => $this->prefix,
			'task' => 'insert'
		);
		
		foreach ($this->fields as $field=>$descr)
			$hash[$field] = '';
		$hash['id'] = 0; // Oh. Of course, this is bad. I know.
		
		webgui_place('status',translate('cms_add'));
		
		foreach($this->fields as $field=>$contents)
			if(is_array($contents))
				$hash["[{$field}]"] = selector($field.'" style="width:240px',$contents,'');
				
		if($load)
			if($data = db_get(template($this->req['edit'],array(
				'table'=>$this->table,
				'fields'=>$this->fields_str,
				'id'=>$id
			)))){
				foreach ($data[0] as $field=>$value)
					$hash[$field] = htmlspecialchars($value);
				foreach ($this->fields as $field=>$contents)
					if (is_array($contents)) {
						$hash["[{$field}]"] = 
							selector(
								$field.'" style="width:240px',
								$contents,$data[0][$field],
								0,translate('none')
							);
						$hash["({$field})"] = 
							isset($contents[$rec[$field]]) 
								? $contents[$rec[$field]] 
								: 'NO DATA '.$rec[$field];
					}
				$status = translate('cms_edit');
				$hash['task'] = 'update';
				$hash['dis'] = ' readonly';
			}
		return tpl($this->prefix.'_edit',$hash);
	}

  // INSERT method
  
  function insert() {
    $form = array();
    foreach($this->fields as $field=>$descr)
      $form[$field] = $_REQUEST[$field].'';
    if(!$form['id']+0)
      if($data = db_get(template($this->req['nextid'],array('table'=>$this->table))))
        $form['id'] = $data[0]['next_id'];
      elseif($data = db_get(template($this->req['maxid'],array('table'=>$this->table))))
        $form['id'] = $data[0]['max_id']+1;
    else
      if($data = (template($this->req['check'],array(
        'table'=>$this->table,
        'id'=>$form['id']+0
      ))))
        return translate('cms_badid').' - '.$data[0]['title'];
    $fields = array();
    $values = array();
    foreach($form as $field=>$value){
      $fields[] = $field;
      $values[] = "'".db_escape(trim($value))."'";
    }
    if(db(template($this->req['insert'],array(
      'table'   => $this->table,
      'fields'  => join(',',$fields),
      'values'  => join(',',$values)
    ))))
      return translate('cms_added');
    else
      return translate('cms_notadded');
  }
  
  // UPDATE method

  function update() {
    $form = array();
    foreach($this->fields as $field=>$descr)
      $form[$field] = $_REQUEST[$field].'';
    if(!db_get(template($this->req['check'],array(
        'table'=>$this->table,
        'id'=>$form['id']+0
      ))))
      return translate('cms_noid');
    $fields = array();
    foreach($form as $field=>$value)
      $fields[] = "{$field}='".db_escape(trim($value))."'";
    if(db(template($this->req['update'],array(
      'table'   => $this->table,
      'id'      => $form['id']+0,
      'set'     => join(',',$fields)
    ))))
      return translate('cms_updated');
    else
      return translate('cms_upderror');
  }

  // DELETE method

  function delete() {
    if(!$this->allow_delete) return translate('cms_denied');
    $id = $_REQUEST['id']+0;
    if(!$id) return translate('cms_del0');
    if(db(template($this->req['delete'],array(
      'table'   => $this->table,
      'id'      => $id
    ))))
      return translate('cms_deleted');
    else
      return translate('cms_nodel');
  }

}


?>
