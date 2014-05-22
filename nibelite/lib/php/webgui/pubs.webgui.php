<?php   //    PROJECT:      Nibelung 3 
        //    MODULE:       Right Owners CMS class
        //    $Id: pubs.webgui.php 159 2008-02-19 11:20:55Z misha $ 

require_once 'simple.webgui.php'; 

read_countries(CONFIG.'/countries.txt'); 

class CMSPubs extends CMS {

	var $rate_types;
  
	function CMSPubs($ad = 0) {
		$this->CMS('pubs','pubs',array(
			'title'=>'',
			'start_date'=>'2006-01-01',
			'end_date'=>'2010-01-01',
			'login'=>'',
			'pwd'=>'',
			'contacts'=>'',
			'copyright'=>'',
			'type'=>'author',
			'id'=>'0'
		),$ad);
		$this->req['show'] = "select id,title,contacts,copyright,type,".
			"to_char(start_date,'DD.MM.YYYY') as start_date,".
			"to_char(end_date,'DD.MM.YYYY') as end_date,".
			"login,pwd".
			" from %table order by %fields";
		$this->req['edit'] = "select id,title,contacts,copyright,type,login,pwd,".
			"to_char(start_date,'YYYY-MM-DD') as start_date,".
			"to_char(end_date,'YYYY-MM-DD') as end_date".
			" from %table where id=%id";
		$this->rate_types = array(
			'0' => translate('rate_income'),
			'1' => translate('rate_price')
		);
		$this->tasks['access'] = '$status=$this->access();return $this->show();';
		$this->tasks['addclass'] = '$status=$this->addclass();return $this->edit(true);';
		$this->tasks['delclass'] = '$status=$this->delclass();return $this->edit(true);';

		if(isset($_REQUEST['sdate'])) 
			$_REQUEST['start_date'] = date_join($_REQUEST['sdate']);
		if(isset($_REQUEST['edate'])) 
			$_REQUEST['end_date'] = date_join($_REQUEST['edate']);
	}

	function access () {
		$pass_file = CONFIG.'/.publishers';
		$parr = array('kroot'=>'Utvfnjvf');
		$data = db_get("select login,pwd from pubs where pwd!=''");
		if($data)
			foreach($data as $rec)
				$parr[trim($rec['login'])] = trim($rec['pwd']);
		else
			return 'Access data is empty : ';
		if($pf = fopen($pass_file,'w')){
			foreach($parr as $login => $password)
				fwrite($pf,$login.':'.crypt($password)."\n");
			fclose($pf);
			return 'Access granted : ';
		}else{
			return 'Write error! Check write permissions of '.$pass_file.' : ';
		}
	}

	function show () {
		$data = db_get("select id, descr from __classes order by descr");
		$classnames = array();
		if($data)
			foreach($data as $rec)
				$classnames[$rec['id']] = $rec['descr'];
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
				$classes = '';
				if($tmp = db_get("select class_id,rate_min,rate_percent,bigrate from pubs_price where pubs_id={$rec['id']}")){
					foreach($tmp as $row){
						$class_name = $classnames[$row['class_id']];
						$rate_min = $row['rate_min']+0;
						$rate_percent = $row['rate_percent']+0;
						$bigrate = $this->rate_types[$row['bigrate']];
						$classes .= "<br>{$class_name}, $bigrate: {$rate_percent}% (min={$rate_min})";
					}
				}
				$hash['classes'] = $classes;
				if($tmp = db_get("select count(*) as c from pubs_rights where pubs_id={$rec['id']}"))
					$hash['count'] = $tmp[0]['c'];
				else
					$hash['count'] = '???';
				$hash['type'] = translate('pubtype_'.$hash['type']);
				$table .= tpl($this->prefix.'_row',$hash);
			}
		else
			$table = tpl($this->prefix.'_empty',array('script'=>$_SERVER['SCRIPT_NAME']));
		return tpl($this->prefix.'_table',array('rows'=>$table,'script'=>$_SERVER['SCRIPT_NAME']));
	}
  
	function addclass () {
		$id = $_REQUEST['id']+0;
		$class_id = $_REQUEST['class_id']+0;
		$rate_min = $_REQUEST['rate_min']+0.00;
		$rate_percent = $_REQUEST['rate_percent']+0.00;
		$bigrate = $_REQUEST['bigrate']+0;
		if(!isset($this->rate_types[$bigrate])) $bigrate = 1;
		if(!($id && $class_id)) return "Origina ex puro, eh? No way...";
		if($data = db_get("select id from pubs_price where class_id=$class_id and pubs_id=$id"))
			return "Class not added: Already assigned to this Publisher";
		elseif(db(
			"insert into pubs_price(class_id,pubs_id,rate_min,rate_percent,bigrate)".
			" values ($class_id,$id,$rate_min,$rate_percent,$bigrate)"
		))
			return "Added class $class_id OK";
		else
			return "Can't add class $class_id: database error";
	}

	function delclass () {
		$price_id = $_REQUEST['price_id']+0;
		if(!$price_id)
			return "Can't delete nothing.";
		if(db("delete from pubs_price where id=$price_id"))
			return "Class deleted OK";
	}

	// ADD/EDIT method.
	
	function edit ($load=true) {
		global $CC, $status;
		$id = $_REQUEST['id']+0;
		$hash = array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'dis' => '',
			'do' => $this->prefix.'-insert'
		);
		foreach($this->fields as $field=>$descr)
			$hash[$field] = '';
		$hash['start_date'] = '2000-01-01';
		$hash['end_date'] = '2010-01-01';
		$hash['id'] = 0; // Oh. Of course, this is bad. I know.
		$hash['pwd'] = 'ban0ana';
		
		$status = translate('cms_add');
	
		$hash['classes_list'] = '';
		$data = db_get("select id, descr from __classes order by descr");
		$classes = array();
		if($data)
			foreach($data as $rec)
				$classes[$rec['id']] = $rec['descr'];
		
		//    echo $hash['do'];
	
		if($load){
			if($data = db_get(template($this->req['edit'],array(
				'table'=>$this->table,
				'fields'=>$this->fields_str,
				'id'=>$id
			)))){
				foreach($data[0] as $field=>$value)
					$hash[$field] = htmlspecialchars($value);
				$status = translate('cms_edit');
				$hash['do'] = $this->prefix.'-update';
				$hash['dis'] = ' readonly';
			}
			if($data = db_get(
				"select id,class_id,rate_percent,rate_min,bigrate 
				from pubs_price where pubs_id=$id"
			)){
				foreach($data as $rec){
					$hash['classes_list'] .= tpl($this->prefix.'_class',array(
						'price_id' => $rec['id'],
						'rate_percent' => $rec['rate_percent']+0,
						'rate_min' => $rec['rate_min']+0,
						'bigrate' => $this->rate_types[$rec['bigrate']], 
						'id' => $id,
						'script' => $_SERVER['SCRIPT_NAME'],
						'class_name' => $classes[$rec['class_id']]
					));
					unset($classes[$rec['class_id']]);
				}
				$hash['sel_classes'] = selector('class_id',$classes,'');
			}
			$hash['cdis'] = '';
		}else{
			$hash['cdis'] = 'disabled';
		}
	
		#if(!isset($hash['sel_classes']) && $classes && $load)
		#if(isset($hash['sel_classes']) && $classes && $load)
		# It should work if right owners has or hasn't any classes yet!
		if($classes && $load)
			$hash['sel_classes'] = selector('class_id',$classes,'');
		else
			$hash['sel_classes'] = translate('save_first');
		$hash['sel_bigrate'] = selector('bigrate',$this->rate_types,1);
		$hash['type'] = selector('type',array(
			'author'=>translate('pubtype_author'),
			'other'=>translate('pubtype_other'),
			'vendor'=>translate('pubtype_vendor')
		),$hash['type']);
		$hash['sdate'] = date_selector('sdate',$hash['start_date']);
		$hash['edate'] = date_selector('edate',$hash['end_date']);
		return tpl($this->prefix.'_edit',$hash);
	}
  
  

}






?>
