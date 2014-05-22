<?php 
// $Id: poprights.php 26 2007-09-19 16:58:45Z misha $
include_once 'init.inc.php'; 
include_once 'webgui/simple.webgui.php';

class EditRights extends CMS {

  var $pubs;

  function EditRights() {
    global $status;
    $this->CMS('erights','erights',array(),0);
    $this->tasks = array(
      'edit'  => 'return $this->edit();',
      'add'  => '$status .= $this->add();return $this->edit();',
      'manage'  => '$status .= $this->manage();return $this->edit();',
    );
    $this->pubs = array();
    if($tmp = db_get("select id,title from pubs order by title"))
      foreach($tmp as $rec)
        $this->pubs[$rec['id']] = $rec['title'];
  }

  function edit() {
    $id = $_REQUEST['id']+0;

    if($data = db_get("select id,pubs_id,to_char(start_date,'YYYY-MM-DD') as sdate,to_char(end_date,'YYYY-MM-DD') as edate,part from pubs_rights where content_id=$id"))
      foreach($data as $rec)
        $list .= tpl($this->prefix.'_row',array(
          'right_id' => $rec['id'],
          'sel_pubs' => selector('pubs_id['.$rec['id'].']',
                     $this->pubs,$rec['pubs_id']),
          'part' => $rec['part']+0,
          'sdate' => date_selector('sdate['.$rec['id'].']',$rec['sdate']),
          'edate' => date_selector('edate['.$rec['id'].']',$rec['edate'])
        ));

    $artist = '?';
    if($data = db_get("select value from meta where content_id=$id and meta.key like 'artist%'")) $artist = $data[0]['value'];
    
    $author = '?';
    if($data = db_get("select value from meta where content_id=$id and meta.key like 'author%'")) $author = $data[0]['value'];
    
    $title = '?';
    if($data = db_get("select value from meta where content_id=$id and meta.key like 'title%'")) $title = $data[0]['value'];

    $ertable = tpl($this->prefix.'_edit',array(
          'id' => $id,
          'sel_pubs' => selector('pubs_id',$this->pubs,0),
          'rights' => $list,
          'script' => $_SERVER['SCRIPT_NAME'],
          'artist' => $artist,
          'author' => $author,
          'title'  => $title
        ));
    return $ertable;
  }

  function add(){
    $hash = array();
    $hash['pubs_id'] = $_REQUEST['pubs_id']+0;
    $hash['content_id'] = $_REQUEST['id']+0;
    $hash['part'] = $_REQUEST['part']+0;
    if(!$data = db_get("select start_date, end_date from pubs where id={$hash['pubs_id']}"))
      return translate('no_owner');
    $hash['start_date'] = $data[0]['start_date'];
    $hash['end_date'] = $data[0]['end_date'];
    if(db(template(
      "insert into pubs_rights (content_id,pubs_id,part,start_date,end_date)".
      " values(%content_id,%pubs_id,%part,'%start_date','%end_date')"
      ,$hash
    )))
      return translate('rights_added');
    else
      return translate('rights_error');
  }
  
  function manage(){
    global $TPL;
    if(!isset($_REQUEST['id'])) return '';
    $id = $_REQUEST['id']+0;
    if($data = db_get("select id from pubs_rights where content_id=$id"))
      foreach($data as $rec){
        $rid = $rec['id'];
        if(isset($_REQUEST['del'][$rid]))
          $this->del_right($rid);
        elseif(
          isset($_REQUEST['part'][$rid]) and
          isset($_REQUEST['pubs_id'][$rid]) and
          isset($_REQUEST['sdate'][$rid]) and
          isset($_REQUEST['edate'][$rid])
        )
          $this->update_right(
            $rid,
            $_REQUEST['part'][$rid],
            $_REQUEST['pubs_id'][$rid],
            $_REQUEST['sdate'][$rid],
            $_REQUEST['edate'][$rid]
          );
      }  
  }
  
  function del_right($id){
    db("delete from pubs_rights where id=$id");
  }
  
  function update_right($id,$part,$pubs_id,$sdate,$edate){
    db("update pubs_rights set part=$part, pubs_id=$pubs_id,".
       "start_date = '".$sdate['y'].'-'.$sdate['m'].'-'.$sdate['d']."',".
       "end_date ='".$edate['y'].'-'.$edate['m'].'-'.$edate['d']."'".
       " where id=$id");
  }

}

$status = '';
$page_main = '&nbsp;';

face_control('content');

$rights = new EditRights();

if(!isset($_REQUEST['do'])) $_REQUEST['do'] = 'erights-edit';

if(!($page_main = $rights->handle()))
  $page_main = '<h1>404 Missed, dude</h1>';

echo template($TPL['popup'],array(
  'page_title'  => 'Àâòîğñêàÿ òğàâà', // äÁ ÎÕ Ë ŞÅÒÔÕ
  'page_main'   => $page_main
));


?>

