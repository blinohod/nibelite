<?php   //      PROJECT:        Nibelung 3
        //      MODULE:         Content CMS class
        //      VERSION: $Id: content.webgui.php 159 2008-02-19 11:20:55Z misha $

include_once 'simple.webgui.php';
include_once 'catalog.webgui.php';
include_once 'storage.api.php';
include_once 'mastering.api.php';
include_once 'compat.api.php';
include_once 'cjsc.inc.php';

class CMSContent extends CMS {

var $profiles;
var $profile;
var $order;
var $orderby;
var $classes;
var $codes;
var $pubs;
var $types;
var $qty;
var $filter;
var $cat;
var $datatpl;
var $dt;
var $picturetypes;
var $apps;
var $compat;

// Constructor

function CMSContent($ad = 1) {

	$this->CMS('content','content',array(
		'class_id'        =>'Class ID',
		'active'          =>'Active flag',
		'created'         =>'Arrived',
		'modified'        =>'Last-modified',
		'id'              =>'ID'
	),$ad);
	$this->req['show'] = 'select distinct content.id from %tables'.
		' %where %order %limit %offset';
	$this->req['count'] = 'select count(distinct content.id) from %tables %where';
		
	// Huge number of default settings outside
	include_once('content.webgui.defaults.php');
		
	// WWW GUI controller part
	$this->tasks = array(
		'list' => 'return $this->show();',
		'gcat' => '$status=$this->group_cat();return $this->show();',
		'guncat' => '$status=$this->group_uncat();return $this->show();',
		'gon' => '$status=$this->group_on();return $this->show();',
		'goff' => '$status=$this->group_off();return $this->show();',
		'add' => 'return $this->edit(false);',
		'edit' => 'return $this->edit();',
		'delete' => '$status=$this->remove();return $this->show();',
		'insert' => '$status=$this->insert();return $this->edit();',
		'update' => '$status=$this->update();return $this->edit();',
		'upload' => '$status=$this->upload();return $this->edit();',
		'deldata' => '$status=$this->delete_data();return $this->edit();',
		'masterdata' => '$status=$this->master_data();return $this->edit();',
		'inlinemaster' => '$this->redir("list",$this->master_data(),false,"c'.$_REQUEST['id'].'");',
		'savemeta' => '$status=$this->save_meta();return $this->edit();',
		'gmeta' => '$status=$this->group_meta();return $this->show();',
		'gmap' => '$status=$this->group_map();return $this->show();',
		'gmapadd' => '$status=$this->group_map_add();return $this->show();',
		'automap' => '$status=$this->automap();return $this->edit();',
		'grights' => '$status=$this->group_rights();return $this->show();',
		'gunrights' => '$status=$this->group_unrights();return $this->show();',

		'jsmapform' => 'return $this->jsmapform();',
		'jsmapdel' => 'return $this->jsmapdel();',
		'jsmapadd' => 'return $this->jsmapadd();',
		'jsmapauto' => 'return $this->jsmapauto();',
		'jsmetaedit' => 'return $this->jsmetaedit();',
		'jsmetasave' => 'return $this->jsmetasave();',
	);

	// Get classes
	$this->classes = array();
	if($tmp = db_get("select id,descr from __classes order by id")) {
		foreach($tmp as $rec) {
			$this->classes[$rec['id']] = $rec['id'].': '.$rec['descr'];
		};
	};

		$this->codes = array();
		if($tmp = db_get("select code, type from rulesmap left join datatypes on type_id=datatypes.id"))
			foreach($tmp as $rec)
				$this->codes[$rec['type']] = $rec['code'];
		$this->pubs = array();
		if($tmp = db_get("select id,title from pubs order by title"))
			foreach($tmp as $rec)
				$this->pubs[$rec['id']] = $rec['title'];
		$this->apps = array();
		if($tmp = db_get("select id,name from apps order by id"))
			foreach($tmp as $rec){
				$this->apps[$rec['id']]['name'] = $rec['name'];
				$this->apps[$rec['id']]['classes'] = array();
				if($tmp1 = db_get("select value from apps_conf where key='map_class' and app_id={$rec['id']}"))
					if($mapclass = explode(',',$tmp1[0]['value']))
						foreach($mapclass as $cla)
							$this->apps[$rec['id']]['classes'][$cla] = 1;
			}

		$this->filter = array();
		$this->filter['class']		= $_REQUEST['fclass'].'';
		$this->filter['search'] 	= $_REQUEST['fsearch'].'';
		$this->filter['data']		= $_REQUEST['fdata'].'';
		$this->filter['prof']   	= $_REQUEST['prof'].'';
		$this->filter['sort']   	= $_REQUEST['fsort'].'';
		$this->filter['cat']    	= $_REQUEST['fcat'].'';
		$this->filter['copy']   	= $_REQUEST['fcopy'].'';
		$this->filter['lim']    	= $_REQUEST['lim'].'';
		$this->filter['ofs']    	= $_REQUEST['ofs'].'';
		$this->filter['active']    	= $_REQUEST['active'].'';
		$this->filter['showimg']    = $_REQUEST['showimg'].'';

		if(!isset($_REQUEST['fclass'])) $this->filter['class'] = $_REQUEST['cont-class'].'';
		if(!isset($_REQUEST['fcode'])) $this->filter['code'] = $_REQUEST['cont-code'].'';
		if(!isset($_REQUEST['fsearch'])) $this->filter['meta'] = $_REQUEST['cont-search'].'';
		if(!isset($_REQUEST['fcat'])) $this->filter['cat'] = $_REQUEST['cont-cat'].'';
		if(!isset($_REQUEST['fcopy'])) $this->filter['copy'] = $_REQUEST['cont-copy'].'';
		if(!isset($_REQUEST['prof'])) $this->filter['prof'] = $_REQUEST['cont-prof'].'';
		if(!isset($_REQUEST['fsort'])) $this->filter['sort'] = $_REQUEST['cont-sort'].'';
		if(!isset($_REQUEST['lim'])) $this->filter['lim'] = $_REQUEST['cont-lim'].'';
		if(!isset($_REQUEST['active'])) $this->filter['active'] = $_REQUEST['cont-active'].'';
		if(!isset($_REQUEST['showimg'])) $this->filter['showimg'] = $_REQUEST['cont-showimg'].'';

		if("{$this->filter['lim']}" == '') $this->filter['lim'] = 50;
		$this->filter['ofs']+=0;
		if("{$this->filter['prof']}" == '') $this->filter['prof'] = $this->profile;
		if(!isset($this->profiles[$this->filter['prof']])) $this->filter['prof'] = 'default';
		$this->filter['showimg'] += 0;

		foreach ($this->filter as $key=>$value)
			setcookie('cont-'.$key,$value);

	}

	// SHOW method.

	function show () {
		global $TPL;
		$this->cat = new CMSCatalog();
		$list = $this->get_list();

		// Make table of content[s]
		$table = '';
		$jstable = '';
		if($list){
			foreach($list as $id)
				$table .= $this->get_row($id);
			$jstable = "'".join("','",$list)."'";
			
		}

		$headers = '<th>ID</th>';
		foreach($this->profiles[$this->filter['prof']] as $col=>$mode)
			if(substr($col,1,1)!='_')
				$headers .= '<th>'.translate('ch_'.$col).'</th>';

		$profilation = array();
		foreach($this->profiles as $key=>$p)
			$profilation[$key] = translate("profile_$key");

        $advstyle = 'closed';
        if($this->filter['cat'] || $this->filter['copy'])
        	$advstyle = 'open';

		$filtertable = template($TPL[$this->prefix.'_filter'],array(
			'script'	=> $_SERVER['SCRIPT_NAME'],
			'sort'		=> $sort,
			'profile'	=> selector('prof',$profilation,$this->filter['prof']),
			'copy'		=> selector('fcopy',
							$this->pubs,$this->filter['copy'],'',translate('any')),
			'active'	=> selector('active',
							array(''=>translate('all'),1=>translate('on'),0=>translate('off')),
							$this->filter['active']),
			'class'		=> selector('fclass',
							$this->classes,$this->filter['class'],'',translate('any')),
			'cat'		=> $this->cat->parent_select(
							$this->filter['cat'],0,'fcat',translate('any'),
							translate('not_in_catalog'),-1),
			'fsearch'	=> $this->filter['search'],
			'lim'		=> $this->filter['lim'],
			'advanced'	=> $advstyle
		));
		
		// CJSC Approach
		$filtertable .= cjsc_loader(true);

		$htmlqty = template($TPL[$this->prefix.'_qty'],array('qty'=>$this->qty));

		$apps = array();
		if($tmp = db_get("select id,name from apps order by id"))
			foreach($tmp as $rec)
				if($rec['id'])
					$apps[$rec['id']] = $rec['id'].': '.$rec['name'];

		$maintable = '';
		if($list)
			$maintable = template($TPL[$this->prefix.'_table'],array(
				'script'	=>	$_SERVER['SCRIPT_NAME'],
				'ids'		=>$jstable,
				'qty'		=>$this->qty,
				'rows'		=>$table,
				'headers'	=>$headers,
				'lim'		=>$this->filter['lim'],
				'ofs'		=>$this->filter['ofs'],
				'app'		=>selector('app_id',$apps),
				'copy'		=>selector('pubs_id',$this->pubs,$this->filter['copy']),
				'parent'	=>$this->cat->parent_select($this->filter['cat']),
				'channels'	=>selector('app_id', $apps, 0, 'NO KEY', '', true)
				// More complex method - including only compatible branches
				// Turned off
				// 'parent'=>$this->cat->class_select(0,$this->filter['class'])
			));

		$navigator = '';
		if($this->qty > $this->filter['lim'])
		$navigator = template($TPL[$this->prefix.'_nav'],array(
			'nav'=>get_navigator(
				$_SERVER['SCRIPT_NAME'].'?do=content-list',
				$this->qty,
				$this->filter['lim'],
				$this->filter['ofs']
			)
		));

		return $filtertable.$htmlqty.$navigator.$maintable.$navigator;
	}

/* Function: get_row($id)
 *
 */
function get_row($id){

	global $TPL;
	$profile = $this->profiles[$this->filter['prof']]; // Get view profile (common, detailed, rights...)

	// If we haven't such content, return empty string
	if (!($content = db_get("select {$this->fields_str} from content where id={$id}"))) {
		return '';
	};

	// Create royalties array if proper profile
	if (isset($profile['royalty'])) {
		if(!($royalty = db_get("select pubs_id, part from pubs_rights where content_id={$id}"))) {
			$royalty = array();
		};
	};

	// Get metadata or create empty array
	if (!($meta = db_get("select key,value from meta where content_id={$id} order by key"))) {
		$meta = array();
	};
		
	$formats = array();
	$data = array();
	$mapping = array();
	$app_ids = array();

	// Create binaries list
	if($bin = new Binary()) {
		$data = $bin->listBinaries($id);
	};
			
		if(isset($profile['mapping'])){
			if($tmp = db_get("select distinct code from mapping where content_id={$id}"))
				foreach($tmp as $rec)
					$mapping[] = $rec['code'];
			if($tmp = db_get("select distinct dst_app_id from mapping where content_id={$id} order by dst_app_id"))
				foreach($tmp as $rec)
					$app_ids[] = $rec['dst_app_id'];
		}			

	if(!($cats = db_get("select cat_id from links where content_id={$id}"))) {
		$cats = array();
	};

	$rowstyle = ($content[0]['active'] === 'f') ? ' style="background:#e0e0e0;"' : '';

	// If user is in technical staff group - allow content removal
	if (access_granted('tech')) {
		$del_str = template(
			$TPL[$this->prefix.'_rowdelact'],
			array(
				'script'=>$_SERVER['SCRIPT_NAME'],
				'id'  =>$id
			)
		);
	};

	$row = "<tr><td{$rowstyle}>".template(
		$TPL[$this->prefix.'_rowact'],
		array(
			'script'=>$_SERVER['SCRIPT_NAME'],
			'id'	=>$id,
			'rowdelact' => $del_str,
		)
	)."</td><td{$rowstyle}>";
		
	$rowset = array();
		foreach($profile as $col=>$mode)
		switch($col) {
			case 'class':
				switch($mode) {
					case 'id':
						$rowset[] = $content[0]['class_id'];
						break;
					case 'class':
						$amap = $this->classes[$content[0]['class_id']];
						if($cats)
							foreach($cats as $rec)
								$amap .= '<br>'.$this->cat->catalog[$rec['cat_id']]['plain'];
						$rowset[] = $amap;
						break;
				}
				break;
			case 'mapping':
				switch($mode) {
					case 'short':
						$amap = '';
						foreach($mapping as $code)
							$amap .= "$code<br>";
						$rowset[] = $amap;
						break;
					case 'long':
						$amap = "<div id=\"map$id\">";
						foreach($mapping as $code)
							$amap .= "$code<br>";
						foreach($app_ids as $app_id)
							$amap .= translate('for').'&nbsp;'.$this->apps[$app_id]['name'].'<br>';
						$amap .= "[<a href=\"#\" onClick=\"".
							cjsc_call("?do=content-jsmapform&id=$id&container=map$id").
							"\">??</a>]";
						$amap .= "</div>";
						$rowset[] = $amap;
						break;
				}
				break;
			case 'meta':
				switch($mode) {
					case 'keys':
						$amap = '';
						foreach($meta as $rec)
							$amap .= $rec['key'].' ';
						$rowset[] = $amap;
						break;
					case 'values':
						$amap = '';
						foreach($meta as $rec)
							$amap .= "{$rec['key']} : ".
								"<span id=\"me{$id}{$rec['key']}\">{$rec['value']}".
								" [<a href=\"#\" onClick=\"".
								cjsc_call("?do=content-jsmetaedit&id=$id&key={$rec['key']}&container=me{$id}{$rec['key']}").
								"\">??</a>]</span><br>";
						$rowset[] = $amap;
						break;
					case 'list':
						$metalist = split(',',$profile['__meta-list']);
						$amap = '';
						foreach($meta as $rec)
							if(in_array($rec['key'],$metalist))
								$amap .= $rec['key'].' : '.$rec['value'].'<br>';
						$rowset[] = $amap;
						break;
				}
				break;
			case 'royalty':
				$aroyal = template($TPL[$this->prefix.'_pubslink'],array(
					'script'=>$_SERVER['SCRIPT_NAME'],
					'id'=>$id
				));
				if($royalty)
					foreach($royalty as $rec)
						$aroyal .= ($rec['part']+0).'% '.
							$this->pubs[$rec['pubs_id']].
							'<br>';
				$rowset[] = $aroyal;
				break;
			case 'data':
				switch($mode) {
					case 'keys':
						$rowset[] = implode(', ',$data);
						break;
					case 'types':
						$mainlist = '';
						$morelist = '';
						$i = 0;
						foreach($data as $data_id=>$data_key){
							// If we are here then $bin is properly initialized
							if($bin->seekById($data_id)){
								$data_type = $bin->type;
								$data_size = $bin->getSize();
							}else{
								$data_type = 'error';
								$data_size = '-';
							}
							$i++;
							
							$func = '';
							if(preg_match('/^master\//',$data_key))
								$func .= tpl($this->prefix.'_inlinemaster',array(
									'data_id'	=> $data_id,
									'id'		=> $id,
									'script'	=> $_SERVER['SCRIPT_NAME']
								));
							if($conv = masterGetConversions($data_key))
								foreach($conv as $conv_key => $conv_title)
									$func .= tpl($this->prefix.'_inlineconv',array(
										'data_id'	=> $data_id,
										'id'		=> $id,
										'script'	=> $_SERVER['SCRIPT_NAME'],
										'format' 	=> $conv_key,
										'title'		=> $conv_title
									));
							
							$subrow = template($TPL[$this->prefix.'_data'],array(
								'data_id'	=> $data_id,
								'key'		=> $data_key,
								'type'		=> $data_type,
								'size'		=> $data_size,
								'func'		=> $func
							));
							if($i<=5)
								$mainlist .= $subrow;
							else
								$morelist .= $subrow;
						}
						if($morelist)
							$mainlist .= tpl($this->prefix.'_datamore',array(
								'id'   	=> $id,
								'list'	=> $morelist
							));
						$rowset[] = $mainlist;
						break;
					case 'picture':
						foreach($data as $data_id=>$data_key)
							if(isset($this->picturetypes[$data_key])){
								if($this->picturetypes[$data_key]){
									$rowset[] = tpl($this->prefix.'_metapicture',array(
										'data_id'   => $data_id,
										'key'		=> $data_key,
										'img'		=> $this->picturetypes[$data_key]
									));
								}else{
									$rowset[] = tpl($this->prefix.'_picture',array(
										'data_id'   => $data_id,
										'key'		=> $data_key
									));
								}
								break;
							}
						break;
				}
				break;
			# END: Main switch
		};

		$row .= join("</td><td{$rowstyle}>",$rowset)."</td></tr>\n";
		return $row;
}

	function checkdata($class_id, $record){
		//'key'  => $rec['key'],
		//'type' => $rec['type'],
		//'size' => $rec['length']
		// write data validation handlers here
		return $record;
	}

	function get_list($nolimit=false){
		global $status;
		$warr = array();
		$where = '';
		$tables = 'content';
		if("{$this->filter['class']}"!='')
			$warr[] = "content.class_id='".db_escape($this->filter['class'])."'";
		if("{$this->filter['active']}" == '1')
			$warr[] = "content.active";
		elseif("{$this->filter['active']}" == '0')
			$warr[] = "not content.active";
		if("{$this->filter['search']}"!=''){
			$wsearch = array();
			$wsearch[] = "meta.value ilike '%".db_escape($this->filter['search'])."%'";
			$tables .= ' left join meta on meta.content_id = content.id';
			if(preg_match('/^\d+$/',$this->filter['search'],$matches)){
				$wsearch[] = "content.id = '".db_escape($this->filter['search'])."'";
				$wsearch[] = "mapping.code like '%".db_escape($this->filter['search'])."%'";
				$tables .= ' left join mapping on mapping.content_id = content.id';
			}
			$warr[] = '('.implode(' or ',$wsearch).')';
		}
		if("{$this->filter['copy']}"!=''){
			$warr[] = "pubs_rights.pubs_id='".db_escape($this->filter['copy'])."'";
			$tables .= ' left join pubs_rights on pubs_rights.content_id = content.id';
		}
		if("{$this->filter['cat']}"!=''){
			$tables .= ' left join links on links.content_id = content.id';
			if($this->filter['cat']!=-1) {
				$warr[] = "(links.cat_id = ".$this->filter['cat']." or links.cat_id in (select id from get_subcatalogs(".$this->filter['cat'].") as id ))";
				//$warr[] = "links.cat_id = ".$this->filter['cat']."";
			} else
				$warr[] = 'links.cat_id is null';
		}

		if($warr){
			$where = 'where '.join(' and ',$warr);
		}
		$order = 'order by ';
		if("{$this->filter['sort']}"!='')
			if(isset($this->orderby[$this->filter['sort']]))
				$order .= $this->orderby[$this->filter['sort']];
    	$order .= 'content.id';
		if($nolimit){
			$offset='';
			$limit='';
		}else{
			$offset = 'offset '.$this->filter['ofs'];
			$limit = 'limit '.$this->filter['lim'];
		}
		$count = db_get(template($this->req['count'],array(
			'tables'	=> $tables,
			'where'		=> $where,
			'order'		=> $order,
			'limit'		=> $limit,
			'offset'	=> $offset,
    	)));
    	$this->qty = ($count) ? $count[0]['count'] : 0;

		$data = db_get(template($this->req['show'],array(
			'tables'	=> $tables,
			'where'		=> $where,
			'order'		=> $order,
			'limit'		=> $limit,
			'offset'	=> $offset,
		)));
		$ids = array();
		if($data)
			foreach ($data as $rec)
				$ids[] = $rec['id'];
		else return false;
		return $ids;
	}

//////////////////////////////////////////////////////
//
//  CJSC Dynamic Routines
//
//////////////////////////////////////////////////////

	function jsmapform () {
		$id = $_REQUEST['id']+0;
		$container = $_REQUEST['container'].'';
		$mapping = '';
		
		if($tmp = db_get("select distinct code from mapping where content_id={$id}"))
			foreach($tmp as $rec)
				$mapping .= $rec['code'].
				"&nbsp;[<a href=\"#\" style=\"color:red\"".
				" onClick=\"".cjsc_call("?do=content-jsmapdel&id=$id&container=$container&code=".
				urlencode($rec['code']))."\">x</a>]<br>";
				
		if($mapping)
			$mapping = "<b>Code list:</b><br><div align=\"right\">$mapping</div>";
		$mapping .= "[<a href=\"#\"".
				" onClick=\"".cjsc_call("?do=content-jsmapauto&id=$id&container=$container")."\"".
				">auto assign</a>]<br>";
		$mapping .= "Manual assign:<br><input type=\"text\" size=\"8\" name=\"im$id\" id=\"im$id\">".
				"[<a href=\"#\" onClick=\"".
				cjsc_call("?do=content-jsmapadd&id=$id&code='+escape(document.getElementById('im$id').value)+'&container=$container").
				"\">save</a>]";
		
		cjsc_respond(cjsc_inject_html($container,$mapping));
	}
	
	function jsmapdel () {
		$id = $_REQUEST['id']+0;
		$code = $_REQUEST['code'].'';
		db("delete from mapping where content_id={$id} and code='".db_escape($code)."'");
		$this->jsmapform();
	}
	
	function jsmapauto () {
		$id = $_REQUEST['id']+0;
		$this->makemapping($id);
		$this->jsmapform();
	}
	
	function jsmapadd () {

		$id = $_REQUEST['id']+0;
		$code = trim($_REQUEST['code']).'';

		if($tmp = db_get("select class_id from content where id=$id limit 1")){
			$class_id = $tmp[0]['class_id'];
			db("delete from mapping where content_id={$id} and code='".db_escape($code)."'");
			foreach($this->apps as $app_id=>$app_data)
				if(isset($app_data['classes'][$class_id]))
					db("insert into mapping (content_id,dst_app_id,code)".
					" values ($id,$app_id,'".db_escape($code)."')");
		}
		$this->jsmapform();
	}
	
	function jsmetaedit () {
		$id = $_REQUEST['id']+0;
		$key = $_REQUEST['key'].'';
		$container = $_REQUEST['container'].'';
		
		$val = '';
		if($tmp = db_get("select value from meta where content_id={$id} and key='".db_escape($key)."' limit 1"))
		    $val = $tmp[0]['value'];
		
		$out = "<input type=\"text\" size=\"40\" name=\"ms{$id}{$key}\" id=\"ms{$id}{$key}\" value=\"{$val}\">".
			"[<a href=\"#\" onClick=\"".
			cjsc_call("?do=content-jsmetasave&id=$id&key=$key&value='".
				"+document.getElementById('ms{$id}{$key}').value+".
				"'&container=$container").
			"\">save</a>]";
		
		cjsc_respond(cjsc_inject_html($container,$out));
	}
	
	function jsmetasave () {
		$id = $_REQUEST['id']+0;
		$key = $_REQUEST['key'].'';
		$value = $_REQUEST['value'].'';
		$container = $_REQUEST['container'].'';
		
		db("update meta set value='".db_escape($value)."' where content_id={$id} and key='".db_escape($key)."'");
		$val = '';
		if($tmp = db_get("select value from meta where content_id={$id} and key='".db_escape($key)."' limit 1"))
			$val = $tmp[0]['value'];
		    
		$out = "{$val} [<a href=\"#\" onClick=\"".
			cjsc_call("?do=content-jsmetaedit&id=$id&key={$key}&container=me{$id}{$key}").
			"\">??</a>]";
		
		cjsc_respond(cjsc_inject_html($container,$out));
	}

//////////////////////////////////////////////////////
//
//  Mass Modification Routines
//
//////////////////////////////////////////////////////

	function get_group () {
		$group = $_REQUEST['sel'];
		if($_REQUEST['what']=='all')
			return $this->get_list(true);
		elseif(is_array($group))
			return array_keys($group);
		else
			return false;
	}

	function group_cat () {
		$cat_id = $_REQUEST['parent_id']+0;
		$ids = $this->get_group();
		foreach($ids as $id)
			if(!($data = db_get("select cat_id from links where cat_id=$cat_id and content_id=$id")))
				db("insert into links (content_id,cat_id) values ($id,$cat_id)");
	} 

	function group_uncat () {
		$cat_id = $_REQUEST['parent_id']+0;
		$ids = $this->get_group();
		if(!$ids) return '';
		foreach($ids as $id){
			db("delete from links where cat_id=$cat_id and content_id=$id");
		}
	}

	function group_meta(){
		$ids = $this->get_group();
		if(!$ids) return '';
		$key = strtolower(trim($_REQUEST['key']));
		$value = trim($_REQUEST['value']);
		if(!$key) return '';
		foreach($ids as $id){
			db("delete from meta where content_id=$id and key='".db_escape($key)."'");
			if($value)
				db("insert into meta (content_id,key,value) values($id,'".
					db_escape($key)."','".db_escape($value)."')");
		}
	}

	function group_map(){
		$ids = $this->get_group();
		$ret = '';
		if(!$ids) return '';
		foreach($ids as $id){
			$ret .= $this->makemapping($id).' ';
		}
		return translate('content_codes_granted').': '.$ret;
	}


/*
 * Function: group_map_add()
 *
 * Duplicate ordering codes for specific channels
 */
function group_map_add(){

	$request_content_ids = $this->get_group();
	$request_channel_ids = $_REQUEST['app_id'];
	$ret = '';

	if ( ! $request_content_ids ) {
		return 'No content to add mapping';
	};

	// Retrieve existing codes for given content
	foreach ( $request_content_ids as $request_content_id ) {
		$select_content_mapping_query = "select code, dst_app_id from mapping
			where content_id = '" . db_escape( $request_content_id ) . "'
			order by code";

		$content_new_channel_ids = $request_channel_ids;

		// Check if we have codes to duplicate 
		if (is_array($select_content_mapping_data = db_get($select_content_mapping_query))) {
			// Обрабатываем для всех кодов заказа
			// ToDo: Добавить удаление начальных и конечных пробелов в кодах заказа!!!
			$select_content_mapping_codes_query = "select code from mapping 
				where content_id='" . db_escape( $request_content_id ) . "'
				group by code;";

			if ( is_array( $codes = db_get( $select_content_mapping_codes_query ) ) ) {
				foreach ($codes as $map_data) {
					$content_code = $map_data['code'];

					foreach ( $content_new_channel_ids as $content_new_channel_id ) {
						$test_if_exist_query = "select oid from mapping
							where content_id='" . db_escape( $request_content_id ) . "'
							and code='" . $content_code . "'
							and dst_app_id='" . db_escape( $content_new_channel_id ) . "'";

						$insert_mapping_query	= "insert into mapping ( content_id, code, dst_app_id )
							values ( '" . db_escape( $request_content_id ) . "', '" . $content_code . "',
							'" . db_escape( $content_new_channel_id ) . "' )";
						if (! is_array(db_get( $test_if_exist_query )) ) {
							db( $insert_mapping_query );
						};

					};
				};
			};
		};
	};

	return translate( 'content_codes_granted' );

}



function automap(){
		$id = $_REQUEST['id']+0;
		if($this->makemapping( $id ))
			return translate('content_codes_granted');
		else 
			return translate('content_error_mapping');
	}

	// DIRTY FIX: VERY simple mappings	
	function makemapping( $content_id ){
		global $status;
		
		if($tmp = db_get("select class_id from content where id=$content_id limit 1"))
			$class_id = $tmp[0]['class_id'];
		else return false;
		
		$newcode = false;
		if($tmp = db_get(
		    "select code from mapping".
		    " where content_id = $content_id and (code like '_____' or code like '______')"
		)){
		    if(strlen($tmp[0]['code']) <= 6){
			$newcode = $tmp[0]['code'];
			db("delete from mapping where content_id = $content_id and code='$newcode'");
		    }
		}
		if(!$newcode){
		    if($tmp = db_get("select nextval('order_codes'::regclass) as newcode"))
			$newcode = sprintf('%05d',$tmp[0]['newcode']);
		    else
			return false;
		}
			
		$retval = false;
		foreach($this->apps as $app_id=>$app_data)
			if(isset($app_data['classes'][$class_id])){
				db("insert into mapping (content_id,dst_app_id,code) values ($content_id,$app_id,'$newcode')");
				$retval = $newcode;
			}
		if(!$retval) $status .= "No apps for class_id $class_id! : ";
		return $retval;
	}

	function group_on(){
		$ids = $this->get_group();
		if(!$ids) return '';
		db("update content set active=true where id in (".join(',',$ids).")");
	}

	function group_off(){
		$ids = $this->get_group();
		if(!$ids) return '';
		db("update content set active=false where id in (".join(',',$ids).")");
	}


	function group_rights(){
		$ids = $this->get_group();
		if(!$ids) return translate('content_not_selected');
		$pubs_id = $_REQUEST['pubs_id']+0;
		$part = $_REQUEST['percent']+0;
		if(!$pubs_id) return translate('invalid_pubs_id').': '.$_REQUEST['pubs_id'];
		$data = db_get("select start_date, end_date from pubs where id=$pubs_id");
		if(!$data) return translate('invalid_pubs_id').': '.$pubs_id;
		$start_date = $data[0]['start_date'];
		$end_date = $data[0]['end_date'];
		foreach($ids as $content_id){
			db("delete from pubs_rights where pubs_id=$pubs_id and content_id=$content_id");
			db_put("insert into pubs_rights (pubs_id,content_id,start_date,end_date,part)".
				" values ($pubs_id,$content_id,'$start_date','$end_date',$part)");
		}
		return translate('content_rights_updated');
	}

	function group_unrights(){
		$ids = $this->get_group();
		if(!$ids) return 'Nothing selected! : ';
		$pubs_id = $_REQUEST['pubs_id']+0;
		$part = $_REQUEST['percent']+0;
		if(!$pubs_id) return "Invalid Publisher's ID: [{$_REQUEST['pubs_id']}] :";
		db("delete from pubs_rights where pubs_id=$pubs_id and content_id in (".implode(',',$ids).")");
		return translate('content_rights_deleted');
	}

	function edit ($load = true) {
		global $TPL, $status;
		$id = $_REQUEST['id']+0;
		$hash = array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'dis' => '',
			'do' => $this->prefix.'-insert'
		);
		foreach($this->fields as $field=>$descr) $hash[$field] = '';
		$hash['id'] = 0; // kinda no record
		$st = translate('cms_add');

		if($load)
			if($data = db_get(template($this->req['edit'],array(
				'table'=>$this->table,
				'fields'=>$this->fields_str,
				'id'=>$id
			)))){
				foreach($data[0] as $field=>$value)
					$hash[$field] = htmlspecialchars($value);
				$st = translate('cms_edit');
				$hash['do'] = $this->prefix.'-update';
				$hash['dis'] = ' readonly';
				$metatable = $this->edit_meta($id);
				//TODO:? copyrights $rightstable = $this->edit_copyright($id,$hash['subject_id']);
				$datatable = $this->edit_data($id,$hash['class_id']);
			}

		$status .= " : $st : ";
		$onoff = array('t' => translate('on'),'f' => translate('off'));
		$hash['class']=selector('class_id',$this->classes,$hash['class_id']);
		$hash['active']=selector('active',$onoff,$hash['active']);

		return template($TPL[$this->prefix.'_edit'],$hash).$metatable.$datatable;
	}

	function update () {
		global $PG;
		$id = $_REQUEST['id']+0;
		$class_id = $_REQUEST['class_id']+0;
		$active = $_REQUEST['active'].'';
		if(!$active) $active='f';
		return $PG[pg_result_status(
			db("update content set class_id=$class_id,active='$active',modified=now() where id=$id")
		)];
	}

	function insert () {
		global $PG;
		$class_id = $_REQUEST['class_id']+0;
		$active = $_REQUEST['active'].'';
		$act = $_REQUEST['active']+0;
		if(!$active) $active='f';
    	
    	if($data = db_get("select nextval('content_id_seq'::regclass) as newid"))
    		$id = $data[0]['newid'];
		else
			return translate('cms_badid');
		
		if(!db("insert into content (id,class_id,active) values ($id,$class_id,'$active')"))
			return translate('cms_error');
		db("insert into meta (content_id,key,value) values ($id,'created','".$_SERVER['REMOTE_USER'].": ' || now())");
		db("insert into meta (content_id,key,value) values ($id,'title','')");
		db("insert into meta (content_id,key,value) values ($id,'artist','')");
		$_REQUEST['id']=$id;
		return translate('content_created');
	}

	function remove (){
		$id = $_REQUEST['id']+0;
		if(!$id) return '';
		db("delete from data where content_id=$id");
		db("delete from meta where content_id=$id");
		db("delete from content where id=$id");
		return translate('cms_delete');
	}

	function edit_meta($id){
		global $TPL;
		$fields = '';
		$i = 0;
		$kv = array();
		if($data = db_get("select key,value from meta where content_id=$id order by key"))
		foreach($data as $rec)
			if(isset($kv[$rec['key']]))
				$kv[$rec['key']] .= ' '.$rec['value'];
			else
				$kv[$rec['key']] = $rec['value'];
		foreach($kv as $key=>$value){
			$hash = array(
				'key' => htmlspecialchars($key),
				'value' => htmlspecialchars($value),
				'i' => $i++
			);
			$fields .= template($TPL[$this->prefix.'_metarow'],$hash);
		}
		return template($TPL[$this->prefix.'_meta'],array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'id' => $id,
			'list' => $fields
		));
	}

	function save_meta(){
		$keys = $_REQUEST['key'];
		$values = $_REQUEST['value'];
		$changed = $_REQUEST['changed'];
		$del = $_REQUEST['del'];
		$newkey = strtolower(trim($_REQUEST['newkey']));
		$newvalue = trim($_REQUEST['newvalue']);
		$id = $_REQUEST['id']+0;
		if(!$this->compat) $this->compat = new Compat();

		if(!$id) return '';

		// update/delete
		if(is_array($keys))
			if($horde = array_keys($keys))
				foreach($horde as $i){
					if($del[$i])
						db("delete from meta where content_id=$id and key='".db_escape($keys[$i])."'");
					elseif($changed[$i]){
						// delete and insert, no updates. (killing dupes)
						db("delete from meta where content_id=$id and key='".db_escape($keys[$i])."'");
						db("insert into meta (content_id,key,value) values($id,'".
							db_escape(trim($keys[$i]))."','".db_escape(trim($values[$i]))."')");
					}
					if(strpos($keys[$i],'compat/')===0)
						if($changed[$i]){
							list($co,$type) = explode('/',$keys[$i]);
							$comp = $this->compat->parseString($values[$i]);
							$this->compat->setIndCompatByKey($id,"content/$type",$comp);
						}elseif($del[$i]){
							list($co,$type) = explode('/',$keys[$i]);
							$this->compat->setIndCompatByKey($id,"content/$type",array());
						}
				}

		// delete/insert
		if($newkey){
			db("delete from meta where content_id=$id and key='".db_escape($newkey)."'");
			db("insert into meta (content_id,key,value) values($id,'".
				db_escape($newkey)."','".db_escape($newvalue)."')");
			if(strpos($newkey,'compat/')===0){
				list($co,$type) = explode('/',$newkey);
				$this->compat->setIndCompatByKey($id,"content/$type",$this->compat->parseString($newvalue));
			}
		}
	}
	
	function edit_data($id,$class_id){
		global $status;
		$this->initdatatpl($class_id);
		if(!$this->compat) $this->compat = new Compat();
		$fields = '';
		
		// using Storage API
		if($bin = new Binary()){
			if($data = $bin->listBinaries($id))
				foreach($data as $data_id=>$data_key){
					if($bin->seekById($data_id)){
						$func = '';
						if(preg_match('/^master\//',$data_key))
							$func .= tpl($this->prefix.'_mastering',array(
								'data_id'	=> $data_id,
								'id'		=> $id,
								'script'	=> $_SERVER['SCRIPT_NAME']
							));
						if($conv = masterGetConversions($data_key))
							foreach($conv as $conv_key => $conv_title)
								$func .= tpl($this->prefix.'_masterconv',array(
									'data_id'	=> $data_id,
									'id'		=> $id,
									'script'	=> $_SERVER['SCRIPT_NAME'],
									'format' 	=> $conv_key,
									'title'		=> $conv_title
								));
						$image = '';
						if($this->filter['showimg'])
							if(preg_match('/^image/',$bin->type))
								$image="<img src=\"/getdata.php?data_id={$data_id}\"><br>";
						$fields .= tpl($this->prefix.'_edatarow',array(
							'key'		=> $data_key,
							'data_id'	=> $data_id,
							'id'		=> $id,
							'type'		=> $bin->type,
							'size'		=> $bin->getSize(),
							'func'		=> $func,
							'image'		=> $image,
							'script'	=> $_SERVER['SCRIPT_NAME']
						));
						if($comp = $this->compat->getIndCompat($data_id))
							$fields .= tpl($this->prefix.'_ecompat',array(
								'compat'	=> $this->compat->createString($comp,true)
							));
					}else{
						$fields .= tpl($this->prefix.'_edatarow',array(
							'key' => $data_key,
							'data_id' => $data_id,
							'id' => $id,
							'type' => translate('content_storage_nodata'),
							'size' => '-',
							'func' => '',
							'image' => '',
							'script' => $_SERVER['SCRIPT_NAME']
						));
					}
				}
		}else{
			$status .= ' : ' . translate('content_error_storage');
		}
		
		$toggleimg = $this->filter['showimg'] ? translate('hide_images') : translate('show_images');
		$antishowimg = $this->filter['showimg'] ? 0 : 1;
		
		return tpl($this->prefix.'_edata',array(
			'script' => $_SERVER['SCRIPT_NAME'],
			'id' => $id,
			'list' => $fields,
			'showimg' => $antishowimg,
			'imgtoggle' => $toggleimg,
			'tpl'=>selector('tpl',$this->dt,'','','--')
		));
	}


	function initdatatpl($class_id){
		if($tmp = db_get(
			"select datatpl.id,mime_types.mime_type,datatypes.type,datatpl.title".
				" from datatpl".
				" left join mime_types on mime_types.id=datatpl.mime_id".
				" left join datatypes on datatypes.id=datatpl.type_id".
				" where datatpl.class_id=$class_id".
				" order by datatpl.title"
		))
		foreach($tmp as $rec){
			$this->datatpl[$rec['id']] = $rec;
			$this->dt[$rec['id']] = $rec['title'];
		}
	}

	function upload(){
		// 1. Checking input data
		
		if(!($id = $_REQUEST['id']+0)) return translate('content_no_id');
		$tpl = $_REQUEST['tpl']+0; 
		
		$class_id = 0;
		if($data = db_get("select class_id from content where id = $id"))
			$class_id = $data[0]['class_id'];
		if($class_id)
			$this->initdatatpl($class_id);
		else
			return translate('content_not_found');
		
		if($tpl and isset($this->datatpl[$tpl])){
			$key = trim($this->datatpl[$tpl]['type']);
			$type = trim($this->datatpl[$tpl]['mime_type']);
		}else{
			if(!($key = strtolower(trim($_REQUEST['key'])))) return translate('content_no_datatype');
			if(!($type = strtolower(trim($_REQUEST['type']))))
				if(!($type = strtolower(trim($_FILES['data']['type']))))
					return translate('content_no_mime');
		}
		
		// 2. Checking uploaded file
		
		if(!($filename = $_FILES['data']['tmp_name'])) return translate('content_no_uploaded_file');
		
		// 3. Storage
		
		if($bin = new Binary())
			if($data_id = $bin->createFromFile($id, $key, $type, $filename))
				return translate('upload_ok');
			else
				return translate('content_file_notsaved');
		else
			return translate('content_error_storage');
	}

	function delete_data(){
		if($data_id = $_REQUEST['data_id']+0)
			if($bin = new Binary())
				if($bin->seekById($data_id))
					if($bin->remove())
						return translate('binary_removed');
					else
						return translate('binary_not_removed');
				else
					return translate('binary_not_found');
			else
				return translate('content_error_storage');
		else
			return translate('content_no_dataid');		 
	}
	
/* ===========================================================================
 * Function: master_data()
 * Parameters: none
 * Return: matering result (string)
 */
function master_data() {

	$do_preview = $_REQUEST['preview']+0;
	$convert = trim($_REQUEST['convert']);

	if ($data_id = $_REQUEST['data_id']+0) {

		if ($id = $_REQUEST['id']+0) {

			if ($bin = new Binary()) {

				if ($bin->seekById($data_id)) {

					if ($convert) {

						if (masterConvert($data_id, $convert)) {
							return 'Convert OK';
						} else {
							return 'Convert failed';
						};

					} else {

						if (masterDeploy($id,$bin->key,$do_preview)) {
							return 'Deployment OK';
						} else {
							return 'Deployment failed';
						};

					};

				} else {
					return translate('binary_not_found');
				};

			} else {
				return translate('content_error_storage');
			};

		} else {
			return translate('content_no_id');
		};

	} else {
		return translate('content_no_dataid');
	};

}

} // class CMSContent extends CMS

?>
