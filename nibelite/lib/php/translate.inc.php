<?php   //      PROJECT:        Nibelung II
        //      MODULE:         Translations CMS class
        //      AUTHOR:		Anatoly 'zmeuka' Matyakh
        //      AUTHOR:

include_once 'simple.inc.php';

class CMSTranslate extends CMS {

  // Constructor
  
  function CMSTranslate() {
    global $LANGUAGE;
    $this->CMS('trans','core.translations',array(),1);
    $this->tasks['list'] = 'return $this->show();';
    $this->tasks['save'] = '$status=$this->save();return $this->show();';
    $this->keyword = strtolower(trim($_REQUEST['keyword']));
    $this->language = strtolower(trim($_REQUEST['language']));
    $this->value = trim($_REQUEST['value']);
    if(strlen($this->language)!=2) $this->language = $LANGUAGE;
  }
  
  function show(){
    global $TPL, $LANGUAGE;
    $fvalue = htmlspecialchars($this->value);
    $fkeyword = htmlspecialchars($this->keyword);
    $flanguage = $this->language;
    $dbkeyword = db_escape($this->keyword);
    $dblanguage = db_escape($this->language);

    if($data = db_get("select value from core.translations where keyword='$dbkeyword' and language='$dblanguage'")){
      $fvalue = htmlspecialchars($data[0]['value']);
    }
    
    $page_main = template($TPL['trans_form'],array(
      'script'      => $_SERVER['SCRIPT_NAME'],
      'keyword'     => $fkeyword,
      'language'    => $flanguage,
      'value'       => $fvalue
    ));

    $table = '';
    $langet = array();

    if($data = db_get("select keyword, language, value from core.translations order by keyword,language")){
      foreach($data as $rec)
        $langet[$rec['language']][$rec['keyword']] = 1;
      foreach($data as $rec){
        if(strlen($rec['value'])>50) $fval = '(big text. click to edit.)'; else $fval=$rec['value'];
        if(!isset($langet[$LANGUAGE][$rec['keyword']]))
          $tr = '&nbsp;[<a href="'.$_SERVER['SCRIPT_NAME'].
            '?keyword='.urlencode($rec['keyword']).
            '&language='.$LANGUAGE.
            '&value='.urlencode($rec['value']).
            '">translate</a>]';
        else $tr = '';
        $table .= template($TPL['trans_row'],array(
          'script'      => $_SERVER['SCRIPT_NAME'],
          'keyword'     => $rec['keyword'],
          'language'    => $rec['language'],
          'translate'   => $tr,
          'value'       => $rec['value']
        ));
      }
    }

    if($table != '') $page_main .= template($TPL['trans_table'],array('rows'=>$table));
    return $page_main;
  }
  
  
  function save(){
    $dbkeyword = db_escape($this->keyword);
    $dblanguage = db_escape($this->language);
    $dbvalue = db_escape($this->value);
    $status = '';
    
    if(($this->keyword!='') and ($this->language!='')){
      if($this->value!=''){
        // save
	if(pg_affected_rows(db("update core.translations set value='$dbvalue' where keyword='$dbkeyword' and language='$dblanguage'"))!=0){
	  $status .= "UPDATED: {$this->keyword}, {$this->language}";
	}elseif(pg_affected_rows(db("insert into core.translations (keyword,language,value) values('$dbkeyword','$dblanguage','$dbvalue')"))!=0){
	  $status .= "INSERTED: {$this->keyword}, {$this->language}";
	}else{
	  $status .= "DB ERROR ON: {$this->keyword}, {$this->language} => {$this->value}";
	}
      }else{
        // kill'em!
	if(pg_affected_rows(db("delete from core.translations where keyword='$dbkeyword' and language='$dblanguage'"))!=0){
	  $status .= "KILLED: {$this->keyword}, {$this->language}";
	}else{
	  $status .= "CAN'T KILL: {$this->keyword}, {$this->language}";
	}
      }
    }else $status = "Strange things!";
    
    return $status;
  }
  
}

?>
