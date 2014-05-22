<?php   //      PROJECT:        nibelung
        //      MODULE:         Partners CMS class
        //      $Id: datatypes.webgui.php 159 2008-02-19 11:20:55Z misha $

include_once 'simple.webgui.php'; 
include_once 'compat.api.php'; 

class CMSTypes extends CMS {

	var $compat;

	function CMSTypes($ad = 0) {
		$this->CMS('type','cms.datatypes',array(
			'type'	=> '',
			'id'	=> '0',
			'descr'	=> ''
		),1);
		$this->compat = new Compat();
	}
  
    function show () {
		global $TPL,$status;
		if(isset($_REQUEST['status'])) $status .= ' : '.$_REQUEST['status'];
		
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
						$head = " [<a href=\"{$_SERVER['SCRIPT_NAME']}?do={$this->prefix}-list&filter=".urlencode($rec[$this->keyfield])."\">";
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
		else
			$data = db_get(template($this->req['show'],array(
				'table'=>$this->table,
				'fields'=>$this->fields_str
			)));
			
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
					
				//-- begin compatibility injection
				
				if($this->compat){
					$comArray = $this->compat->getTypeCompat($hash['id']);
					if($comArray){
						$hash['compat'] = $this->compat->createString($comArray);
						$hash['htmlcompat'] = $this->compat->createString($comArray,true);
						$hash['htmlcompat'] = str_replace('; ',';<br>',$hash['htmlcompat']);
					}else{
						$hash['compat'] = '';
						$hash['htmlcompat'] = "Nothing";
					}
				}else{
					$hash['compat'] = '';
					$hash['htmlcompat'] = "Compat() not initialized";
				}
				
				//-- end compatibility injection
					
				$table .= template($TPL[$this->prefix.'_row'],$hash);
			}
		else
			$table = template(
				$TPL[$this->prefix.'_empty'],
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
	
		return template($TPL[$this->prefix.'_table'],$hash);
	}
	
	function update() {
		$retval = parent::update();
		if($this->compat && isset($_REQUEST['compat']) && isset($_REQUEST['id'])){
			$comp = $this->compat->parseString($_REQUEST['compat']);
			$id = $_REQUEST['id']+0;
			if($id)
				if($this->compat->setTypeCompat($id,$comp))
					$retval .= " + compatibility OK";
				else
					$retval .= " + compatibility error: " . $this->compat->error;
			else
				$retval .= " + no datatype ID error";
		}
		return $retval;
	}
	
}

?>
