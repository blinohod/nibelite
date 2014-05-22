<?php
/*
 * $Id: catalog.webgui.php 159 2008-02-19 11:20:55Z misha $
 *
 * Catalog CMS class.
 */

include_once 'simple.webgui.php';
include_once 'cjsc.inc.php';


class CMSCatalog extends CMS {

var $filter;
var $tree;
var $catalog;
var $page_class_id;

// Constructor
function CMSCatalog() {

	// Initialize GUI controller
	$this->CMS('cat','cms.catalog',array(),0); // just for fun
	$this->tasks = array(
		'list' => 'return $this->show();', // show catalog page
		'add' => '$this->add();return $this->show();', // add new catalog node
		'save' => '$this->save();return $this->show();', // save catalog node
		'del' => '$this->del();return $this->show();', // remove catalog node
		'update' => '$this->update_tree();return $this->show();', // updage catalog tree cached info
		'up' => '$this->up();return $this->show();', // move item up (sort++)
		'down' => '$this->down();return $this->show();', // move item down (sort--)
		'copy' => '$this->copytreestart();$this->load_tree();return $this->show();', // copy subtree

		'pageadd' => '$this->pageadd(); $this->load_tree(); return $this->show();', // move item down (sort--)
	);

	$this->load_tree();
	$this->filter['id'] = $_REQUEST['id']+0;
	$this->filter['parent_id'] = $_REQUEST['parent_id']+0;
	$this->filter['title'] = trim($_REQUEST['title'].'');
	$this->filter['keywords'] = trim($_REQUEST['keywords'].'');
	$this->filter['sort'] = $_REQUEST['sort']+0;
	$this->filter['dir'] = trim($_REQUEST['dir'].'');
	$this->filter['node_class'] = trim($_REQUEST['node_class'].'');
	$this->filter['saveadd'] = trim($_REQUEST['saveadd'].'');

} // function CMSCatalog()



/* ===========================================================================
 * Function: up()
 *
 * Move catalog node up (incremet sort field).
 */
function up() {

	$id = $this->filter['id'];
	if (!$id) {
		return ''; // cant move unexistent node
	};

	// Simply update
	db("update cms.catalog set sort=sort+1 where id=$id");
	$this->load_tree();

} // function up()



/* ===========================================================================
 * Function: down()
 *
 * Move catalog node up (decremet sort field).
 */
function down() {

	$id = $this->filter['id'];
	if (!$id) {
		return ''; // cant move unexistent node
	};

	// Decrement sort
	db("update cms.catalog set sort=greatest(sort-1, 0) where id=$id");
	$this->load_tree();

} // function down()



function save () {
    $id = $this->filter['id'];
    if(!$id) return '';
    $parent_id = $this->filter['parent_id'];
    $title = db_escape($this->filter['title']);
    $keywords = db_escape($this->filter['keywords']);
		$dir = db_escape(preg_replace('/[^a-z0-9\_]/','',strtolower($this->filter['dir'])));
		$node_class = db_escape($this->filter['node_class']);

    db("update cms.catalog set parent_id=$parent_id, title='$title',".
       " keywords='$keywords', dir='$dir', node_class='$node_class' where id=$id");
        if($this->filter['saveadd'] == '')
      $this->load_tree();
        else
          $this->add( $parent_id );
  }


  function add ( $parent_id = '' ) {
    if($parent_id == ''){
      $id = $this->filter['id'];
        }else{
          $id = $parent_id + 0;
        }
    $title = '* * * New Level * * *';
    if(!$id) return '';
    $data = db_get("insert into cms.catalog (parent_id,title) values ($id,'$title') returning id");
    if($data){
      $this->filter['id'] = $data[0]['id'];
    }
    $this->load_tree();
}



/* ===========================================================================
 *
 */
function del () {

	$id = $this->filter['id'];
	if(!$id) return '';
	foreach($this->catalog as $rec)
	if($rec['parent_id'] == $id) return '';
	db("delete from links where cat_id=$id");
	db("delete from catalog where id=$id");
	$this->filter['id'] = $this->catalog[$id]['parent_id'];
	$this->load_tree();

} // function del ()




/* ===========================================================================
 * Function: show()
 *
 * Method show() 
 */
function show () {

	global $TPL;

	// FIXME: Dirty hack for new pages creation.
	$data = db_get("select id from __classes where class='page-descriptor'");
	$this->page_class_id = $data[0]['id'];

	$head = $this->filter['id'];
	while ($this->catalog[$head]['parent_id']) {
		$head = $this->catalog[$head]['parent_id'];
	};

	$list = cjsc_loader(true);

	// Add catalog items (tree on the left side)
	foreach ($this->tree as $id=>$subtree) {
		$list .= $this->get_item($id,'#cccccc');
		if ($id == $head) { $list .= $this->get_list($subtree); };
 	};

	// Add form for current catalog id.
	$form = $this->get_form($this->filter['id']);

	// Return data as template processed string.
	return template($TPL[$this->prefix.'_page'],array(
		'list' => $list,
		'form' => $form
	));

} // function show ()




function get_form($id){

    global $TPL;
    if(!$id) return '&nbsp;';
    $rec = $this->catalog[$id];

		$lp = '[<a href="?do=cat-pageadd&id=' . $id . '">'. translate('create').'</a>]';
		if ($this->catalog[$id]['linked_page_id']) {
			if ($meta = db_get("select * from meta where content_id = " . $this->catalog[$id]['linked_page_id']
				. " and key like 'title%' limit 1")) {
					$lp = "title: ".$meta[0]['value'] . "<br>";
			};

			$lp .= '[<a href="/index.php?do=content-edit&id='.$this->catalog[$id]['linked_page_id'] . '">'. translate('edit').'</a>]';
		};

    return template($TPL[$this->prefix.'_form'],array(
      'script' => $_SERVER['SCRIPT_NAME'],
      'id' => $id,
      'title' => htmlspecialchars($rec['title']),
      'keywords' => htmlspecialchars($rec['keywords']),
      'dir' => htmlspecialchars($rec['dir']),
      'node_class' => htmlspecialchars($rec['node_class']),
			'parent' => $this->parent_select($rec['parent_id'],$id),
			'linked_page' => $lp,
    ));
  }

  function parent_select ($parent,$exclude=0,$name='parent_id',$empty='',$addtitle='',$addval=''){
    $select = '<select name='.$name.' style="width: 200px">';
    if($empty){
      $select .= '<option value=""';
      if($parent=='') $select .= ' selected';
      $select .= '>'.$empty;
    }
    if($addtitle){
      $select .= '<option value="'.$addval.'"';
      if($parent==$addval) $select .= ' selected';
      $select .= '>'.$addtitle;
    }
    $select .= '<option value=0';
    if("$parent"=="0") $select .= ' selected';
    $select .= '>'.translate('root');
    $select .= $this->parent_options($this->tree,$parent,$exclude);
    $select .= '</select>';
    return $select;
  }

  function parent_options($tree,$sel,$exclude){
    $options = '';
        $style = array(
          1 => ' style="font-weight: bold; background: #cccccc;"',
          2 => ' style="font-weight: bold;"'
        );
    foreach($tree as $id=>$subtree){
      if($id!=$exclude){
        $shift = '';
        for($i=0;$i<$this->catalog[$id]['shift'];$i++) $shift.='&bull;&nbsp;';
        $options .= '<option value='.$id;
        if($id == $sel) $options .= ' selected';
                $st = $style[$this->catalog[$id]['shift']].'';
        $options .= $st.'>'.$shift.$this->catalog[$id]['title'];
        if($subtree)
          $options .= $this->parent_options($subtree,$sel,$exclude);
      }
    }
    return $options;
  }

  // Content-class selector

  function class_select ($parent,$class=0,$name='parent_id',$empty=''){

//    print "<pre>Temporary Maintenance mode, sorry.\n";
//    print_r($this->catalog);
//    print "</pre>";

    $select = '<select name='.$name.' style="width: 200px">';
    if($empty){
      $select .= '<option value=""';
      if($parent=='') $select .= ' selected';
      $select .= '>'.$empty;
    }
    $select .= $this->class_options($this->tree,$parent,$class);
    $select .= '</select>';
    return $select;
  }

  function class_options($tree,$sel,$class){
    $options = '';
        $style = array(
          1 => ' style="font-weight: bold; background: #cccccc;"',
          2 => ' style="font-weight: bold;"'
        );
    foreach($tree as $id=>$subtree){
      if((!($class+0))||($this->catalog[$id]['classes'][$class])){
        $shift = '';
        for($i=0;$i<$this->catalog[$id]['shift'];$i++) $shift.='&bull;&nbsp;';
        $options .= '<option value='.$id;
        if($id == $sel) $options .= ' selected';
                $st = $style[$this->catalog[$id]['shift']].'';
        $options .= $st.'>'.$shift.$this->catalog[$id]['title'];
        if($subtree)
          $options .= $this->class_options($subtree,$sel,$class);
      }
    }
    return $options;
  }

// Copy Catalog Tree

  function copytreestart($tree = 0){
    if(!$tree) $tree = $this->tree;
    $sid = $this->filter['id'];
    foreach($tree as $id=>$subtree)
      if($id==$sid){
        $this->copytree($id,-1,$subtree);
        return;
      }elseif($subtree)
        $this->copytreestart($subtree);
    $this->load_tree();
  }

  function copytree($id,$new_parent,$tree){
    global $status;
    if($new_parent==-1){
      $new_parent = 'parent_id';
    }

    // copy current level
    $oid = db_put("insert into catalog (parent_id,title,keywords,dir,sort)".
                  " select $new_parent,title,keywords,dir,sort from catalog where id=$id");

    if($tmp = db_get("select id from catalog where oid=$oid")){
      $new_id = $tmp[0]['id'];
      if($id == $this->filter['id'])
        db("update catalog set title = 'Copy of ' || title, dir = 'copy_' || dir where id=$new_id");
      if($tree)
        foreach($tree as $sid=>$subtree)
          $this->copytree($sid,$new_id,$subtree);
    }else{
      $status .= "error@$id ";
    }
  }

  function get_list ($tree){
    $list = '';
    foreach($tree as $id=>$subtree){
      $list .= $this->get_item($id,'#ccddee');
      if($subtree)
        $list .= $this->get_list($subtree);
    }
    return $list;
  }

  function get_item ($id,$color) {
    global $TPL;
    if(!isset($this->catalog[$id])) return '';
    $title = $this->catalog[$id]['title'];
    if($id == $this->filter['id'])
      $title = "<b style='color:red;'>-&raquo;&nbsp;{$title}</b>";
    $shift = '';
    for($i=0;$i<$this->catalog[$id]['shift'];$i++) $shift.='&nbsp;&nbsp;';
    return template($TPL[$this->prefix.'_item'],array(
      'script' => $_SERVER['SCRIPT_NAME'],
      'id' => $id,
      'title' => $title,
      'keywords' => $this->catalog[$id]['keywords'],
      'dir' => $this->catalog[$id]['dir'],
      'sort' => $this->catalog[$id]['sort'],
      'shift' => $shift,
      'node_class' => $this->catalog[$id]['node_class'],
      'color' => $color
    ));
  }

  function update_tree(){
    global $status;

    // Meta-pass 1: calculating...

    foreach($this->catalog as $id=>$rec){
      $this->catalog[$id]['plain'] = $rec['title'];

      // Unfortunately 2 passes... eh.

      $par = $rec['parent_id'];
      while($par){
        $this->catalog[$id]['plain'] = $this->catalog[$par]['title'].'/'.$this->catalog[$id]['plain'];
        foreach($this->catalog[$par]['support'] as $class=>$kxe) $this->catalog[$id]['classes'][$class]=1;
        $par = $this->catalog[$par]['parent_id'];
      }

      $par = $rec['parent_id'];
      while($par){
        foreach($this->catalog[$id]['classes'] as $class=>$kxe) $this->catalog[$par]['classes'][$class]=1;
        $par = $this->catalog[$par]['parent_id'];
      }
    }

    foreach($this->catalog as $id=>$rec){
      $db_fullpath = db_escape($rec['plain']);
      if($rec['classes'])
      	$db_classes = db_escape(implode(',',array_keys($rec['classes'])));
      else
      	$db_classes = '';
      $db_shift = $rec['shift'] + 0;
      db("update catalog set fullpath='$db_fullpath', classes='$db_classes', shift=$db_shift where id=$id");
    }

    $status .= "Tree saved";
  }



function load_tree() {

	$this->tree = array();
	$this->catalog = array();

	$linked_pages = array();

	// Get page descriptors for linking from admin GUI
	if ($data = db_get("select c.id, l.content_id from catalog c
		join links l on (l.cat_id = c.id)
		join content cn on (cn.id = l.content_id)
		join __classes cl on (cl.class = 'page-descriptor' and cn.class_id = cl.id)")) {

		foreach ($data as $rec) {
			$linked_pages[$rec['id']] = $rec['content_id'];
		};

	};

	if ($data = db_get("select id, parent_id, title, keywords, dir, sort, classes, fullpath, node_class from catalog")) {

    foreach($data as $rec) {
      $id = $rec['id'];
      $this->catalog[$id] = $rec;
      $this->catalog[$id]['classes'] = array();
      $this->catalog[$id]['plain'] = $rec['fullpath'];

			// Add linked page descriptor ID if exists
			if ($linked_pages[$id]) {
				$this->catalog[$id]['linked_page_id'] = $linked_pages[$id];
			};

      $classes = explode(',',$rec['classes']);
      foreach($classes as $class) {
				$this->catalog[$id]['classes'][$class] = 1;
			};
      $this->catalog[$id]['support'] = array();

      $classes = explode(',',$rec['keywords']);
      foreach($classes as $class) {
				$this->catalog[$id]['support'][$class] = 1;
			};

		};

	} else {
    return;
	};

	$this->tree = $this->build_tree(0,0);

} // function load_tree()



/*
 *
 */
function build_tree($level,$shift) {

	// Implement protection against too deep recursion
	if (isset($this->catalog[$level]['shift'])) {
		return array(); // really deep now
	};

	$this->catalog[$level]['shift'] = $shift;

	// Fetch child nodes
	$children = array();
	foreach ($this->catalog as $id => $rec) {
		if(($rec['parent_id'] == $level) and ($id!=0)) {
			$children[] = $rec;
		};
	};

	if (!$children) {
		return array();
	};

	usort($children, create_function('$a,$b','return $b["sort"] - $a["sort"];'));

	// Build catalog structure
	$bunch = array();
	foreach ($children as $rec) {
		$bunch[$rec['id']] = $this->build_tree($rec['id'],$shift+1);
	};

	return $bunch;

} // function build_tree($level,$shift)



/* ===========================================================================
 *
 */
function pageadd() {

	// FIXME: Dirty hack for new pages creation.
	$data = db_get("select id from __classes where class='page-descriptor'");
	$this->page_class_id = $data[0]['id'];

	$oid = db_put("insert into content (class_id) values (" . $this->page_class_id . ")");
	$data = db_get("select id from content where oid = $oid");
	$content_id = $data[0]['id'];

	db_put("insert into links (cat_id, content_id) values ({$this->filter['id']}, $content_id)");

	db_put("insert into meta (content_id, key, value) values ($content_id, 'title.ru', 'Russian title')");
	db_put("insert into meta (content_id, key, value) values ($content_id, 'title.ua', 'Ukrainian title')");
	db_put("insert into meta (content_id, key, value) values ($content_id, 'title.en', 'English title')");

	db_put("insert into meta (content_id, key, value) values ($content_id, 'body.ru', 'Russian body')");
	db_put("insert into meta (content_id, key, value) values ($content_id, 'body.ua', 'Ukrainian body')");
	db_put("insert into meta (content_id, key, value) values ($content_id, 'body.en', 'English body')");


} // function pageadd()

} // class CMSCatalog

?>
