<?php
/* ===========================================================================
 *
 * MODULE
 * 	
 * 	core.inc.php
 *
 * DESCRIPTION
 *
 * 	This module contains common functionality for all other components.
 *
 * AUTHORS
 *
 *	Anatoly Matyakh <zmeuka@x-play.com.ua>
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 * TODO
 *
 *	1. Full refactor and replacing some unused functions.
 * 
 * ===========================================================================
 */

$REPLACE_GLOBAL = array(
	'SELF' => $_SERVER['PHP_SELF']
);

// FUNCTION: translit ( $cp1251_str )
// MODULE: language tweaking
// DO: english-like cyrillic transliteration
/* ===========================================================================
 * Function: translit($cp1251_str)
 */
function translit($str){
  $rus = array(
    'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П',
    'Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я',
    'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п',
    'р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я','Ґ','ґ','Є','є','Ї','ї'
  );
  $lat = array(
    'A','B','V','G','D','E','Yo','Zh','Z','I','Y','K','L','M','N','O','P',
    'R','S','T','U','F','H','C','Ch','Sh','Sch','','I','','E','Yu','Ya',
    'a','b','v','g','d','e','yo','zh','z','i','y','k','l','m','n','o','p',
    'r','s','t','u','f','h','c','ch','sh','sch','','i','','e','yu','ya', 'G', 'g', 'E', 'e', 'Yi', 'yi'
  );
  return str_replace($rus,$lat,$str);
}
// Translation routines

// Init_language: load the desired language into $LANG hash

function init_language($lang = 'en', $service='core') {

  global $LANG, $DEFAULT_LANGUAGE;
  $LANG = array();

	if ($data = db_get("select keyword,value from core.translations where lang = '$lang' and service in('', '$service')")) {
    foreach ($data as $rec) {
			$LANG[$rec['keyword']] = $rec['value'];
		};
		return true;
	} elseif($lang != $DEFAULT_LANGUAGE) {
		return init_language($DEFAULT_LANGUAGE);
	} else {
		return false;
	};

}

// Translate something by keyword

function translate($keyword) {
  global $LANG;
  if(translated($keyword))
    return $LANG[$keyword];
  else
    return "Translate: $keyword";
}

// Check if a term is translated

function translated($keyword) {
  global $LANG;
  return isset($LANG[$keyword]);
}


// HTMP-Templates handling routines

//////////////////////////////////////////////////////#
//
//   read_templates ( $filename, [$template_name] )
//
//   Reads templates into $TPL hash
//

function read_templates ($filename, $tpl_name='') {
  global $TPL, $LANG;
  if(!($FILE = fopen($filename,'r'))){
    print "Can't open template file $filename";
    return false;
  }
  if($tpl_name){
    $TPL[$tpl_name] = '';
  }
  while(!feof($FILE)){
    $str = fgets($FILE);
    if(preg_match('/^\s*\#TEMPLATE\s+(\w+)/i',$str,$matches)){
      $tpl_name = $matches[1];
      $TPL[$tpl_name] = '';
    }elseif(preg_match('/^\s*\<\!--\#TEMPLATE\s+(\w+)/i',$str,$matches)){
      $tpl_name = $matches[1];
      $TPL[$tpl_name] = '';
    }elseif(preg_match('/^\s*\#TRANSLATE\s+(\w+)\s*(.*)$/i',$str,$matches)){
      $LANG[$matches[1]] = $matches[2];
    }elseif(preg_match('/^\s*\<\!--\#TRANSLATE\s+(\w+)\s*(.*)\s*--\>$/i',$str,$matches)){
      $LANG[$matches[1]] = $matches[2];
    }elseif(preg_match('/^\s*\#/',$str)){
      // just a comment - do nothing
    }elseif($tpl_name){
      $TPL[$tpl_name] .= $str;
    }
  }
  fclose($FILE);
  return true;
}

// XML Constructor
function xml_document ($body,$encoding='cp1251'){
  return '<?xml version="1.0" encoding="'.$encoding.'"?>'.
         "\n".$body;
}

// XML Tag
function xml_tag ($tag,$param=0,$body=''){
  $par_line = '';
  if(is_array($param))
    foreach($param as $par=>$val)
      $par_line .= ' '.strtolower($par).'="'.htmlspecialchars($val).'"';
  $retval = '<'.strtolower($tag).$par_line;
  if($body)
    $retval .= ">\n".$body."</".strtolower($tag).">\n";
  else
    $retval .= "/>\n";
  return $retval;
}

//////////////////////////////////////////////////////#
//
//   template
//   ( $template, $replace_hash )
//
//   Returns an parsed template with all keys from given hash
//   replaced to associated values. Useful for filling forms,
//   pre-designed web output, interfaces etc.
//
//   CASE SENSITIVE!
//

function template($template,$replace = array()) {

  $tmp = $template;
  if($replace) {
		foreach ($replace as $k => $v) {
			$tmp = str_replace('%'.$k,$v,$tmp);
		};
	};
	return $tmp;

}

function tpl($template,$replace = array()){
	global $TPL, $REPLACE_GLOBAL;
	if(isset($TPL[$template])){
		$tmp = $TPL[$template];
		if($replace)
			foreach($replace as $k => $v)
				$tmp = str_replace('%'.$k,$v,$tmp);
		if($REPLACE_GLOBAL)
            foreach($REPLACE_GLOBAL as $k => $v)
                $tmp = str_replace('%'.$k,$v,$tmp);

	}else{
		$tmp = "Template \$TPL['$template'] is not set.";
		if($replace){
			$tmp .= " Failed substitutions: ";
			foreach($replace as $k => $v)
				$tmp .= "%$k => '$v', ";
		}
	}
	return $tmp;
}


//////////////////////////////////////////////////////#
//
//   read_countries ( $filename )
//
//   Reads Country list
//

function read_countries ($filename) {
	global $CC;
	if(!($FILE = fopen($filename,'r'))){
		print "Can't open template file $filename";
		return false;
	}
	while(!feof($FILE)){
		list($ccode,$country) = split(':',fgets($FILE));
		if(($ccode!='') and ($country!=''))
			$CC[$ccode] = $country;
	}
	fclose($FILE);
	return true;
}



function showxml($xml){
	$search = array(
		'/\&lt\;([\w\-]+)(.*?)([^\/])\&gt\;/',
		'/\&lt\;(\/[\w-]+)\s*\&gt\;/',
		'/\&gt\;\s*\&lt\;/',
		'/\&lt\;!\[cdata\[/i',
		'/\]\]\&gt\;/'
	);
	$replace = array(
		'&lt;$1$2$3&gt;<dl style="margin:0px;"><dd style="margin:0px 0px 0px 1em;">',
		'</dd></dl>&lt;$1&gt;',
		'&gt;<br>&lt;',
		'&lt;<b>![CDATA[</b><dl style="margin:0px;"><dd style="margin:0px 0px 0px 1em;color:gray;">',
		'</dd></dl><b>]]</b>&gt;'
	);
	return preg_replace($search,$replace,htmlspecialchars($xml));
}

//////////////////////////////////////////////////////#
//
//   selector
//   ( $name, $hash, [$default, [$nokey, $novalue]], [$multi] )
//
//   Returns an HTML <SELECT> statement filled with keys and
//   values of $hash. <OPTION> where key is equal to $default
//   is marked as SELECTED.
//

function selector($name,$hash,$default=0,$nokey='NO KEY',$novalue='',$multi=false){
	if ( $multi ) {
		$multi_attribute	= 'multiple';
		$name			.= '[]';
	}

	$select = "<select name=\"{$name}\" {$multi_attribute}>\n";
	if($nokey!='NO KEY'){
		$select .= '<option value="'.htmlspecialchars($nokey).'"';
		if("$nokey" == "$default") $select .= ' selected';
		$select .= '>'.htmlspecialchars($novalue);
	}
	if(is_array($hash))
		foreach($hash as $key=>$value){
			$select .= '<option value="'.htmlspecialchars($key).'"';
			if("$key"=="$default") $select .= ' selected';
			$select .= '>'.htmlspecialchars($value);
		}
	$select .= "</select>\n";
	return $select;
}



/////////////////////////////////////
//
//   date selector
//   ( $name, $date )
//   $date in SQL format: 'YYYY-MM-DD'
//

function date_selector($name,$date,$addtime=false){

	global $YEARS,$MONTHS,$DAYS;

  list($y,$m,$d) = split('-',substr($date,0,10));
  if("$y"=='') $y = '2007';
  if("$m"=='') $m = '01';
  if("$d"=='') $d = '01';
  $datesel = selector($name.'[d]',$DAYS,$d);
  $datesel .= selector($name.'[m]',$MONTHS,$m);
  $datesel .= selector($name.'[y]',$YEARS,$y);
  if($addtime){
    list($hh,$mm,$ss) = split(':',substr($date,11,8));
    $hours=array();for($i=0;$i<24;$i++){$ik=sprintf("%02s",$i);$hours[$ik]=$ik;}
    $x60=array();for($i=0;$i<60;$i++){$ik=sprintf("%02s",$i);$x60[$ik]=$ik;}
    if("$hh"=='') $hh = '00';if("$mm"=='') $mm = '00';if("$ss"=='') $ss = '00';
    $datesel .= '&nbsp;'.selector($name.'[hh]',$hours,$hh);
    $datesel .= selector($name.'[mm]',$x60,$mm);
    $datesel .= selector($name.'[ss]',$x60,$ss);
  }
  return $datesel;
}

function date_join($d){
  $date = '2001-01-01 00:00:00';
  if(is_array($d)){
    if(isset($d['y'])) $date = substr_replace($date,sprintf("%04d",$d['y']),0,4);
    if(isset($d['m'])) $date = substr_replace($date,sprintf("%02d",$d['m']),5,2);
    if(isset($d['d'])) $date = substr_replace($date,sprintf("%02d",$d['d']),8,2);
    if(isset($d['hh'])) $date = substr_replace($date,sprintf("%02d",$d['hh']),11,2);
    if(isset($d['mm'])) $date = substr_replace($date,sprintf("%02d",$d['mm']),14,2);
    if(isset($d['ss'])) $date = substr_replace($date,sprintf("%02d",$d['ss']),17,2);
  }
  return $date;
}

function get_navigator($script,$total,$size,$pos){
  if(!$size) $size = 50;
  $num_pages = (integer) ($total/$size);
  if($total % $size) $num_pages++;
  $current_page = (integer) ($pos/$size);
  if($pos % $size) $current_page++;
  if($current_page<5){
    $start = 0;
    if($num_pages>4) $finish=4; else $finish=$num_pages-1;
  }elseif($current_page>$num_pages-5){
    $start = $num_pages-5;
    $finish = $num_pages-1;
  }else{
    $start = $current_page-2;
    $finish = $current_page+2;
  }
  if($start) $leading = make_link($script,'&laquo;&laquo;',$start-1,$size,$total);
    else $leading='';
  if($finish<($num_pages-1)){
    $trailing = make_link($script,'&raquo;&raquo;',$finish+1,$size,$total);
    if($finish<($num_pages-2))
      $trailing .= ' &nbsp '.make_link($script,'&raquo;]',$num_pages - 1,$size,$total);
  }else $trailing='';
  $nav = ' &nbsp; ';
  for($i=$start;$i<=$finish;$i++){
    if($i == $current_page) $nav .= make_link('','',$i,$size,$total);
      else $nav .= make_link($script,'',$i,$size,$total);
    $nav .= ' &nbsp ';
  }
  return $leading.$nav.$trailing;
}

function make_link($script,$title,$page,$size,$total){
  $a = $page * $size;
  $a1 = $a+1;
  $b = ($page+1) * $size;
  if(!$title){
    if($b>$total) $b = $total;
    $title = $a1.'..'.$b;
  }
  if($script)
    return '<a href="'.$script.'&ofs='.$a.'&lim='.$size.'">'.$title.'</a>';
  else
    return '<b>'.$title.'</b>';
}

function button($caption,$do,$par=''){
  if($par != '')
    if(substr($par,0,1) != '&')
      $par = '&'.$par;
  return '[<a href="'.$_SERVER['SCRIPT_NAME'].'?do='.$do.$par.'">'.$caption.'</a>] ';
}

/////////////////////////////////////
//
//   updates logger
//   ( $sql )
//   $sql - an SQL command to perform update
//

function log_update($sql){
  db("insert into partner_updates (upd_sql) values('".db_escape($sql)."')");
}

// Make a chain directory structure like "xxx/yyy/zzz"

function make_path($dir, $mode = 0775)
{
  if (is_dir($dir) || @mkdir($dir,$mode)) return TRUE;
  if (!make_path(dirname($dir),$mode)) return FALSE;
  return @mkdir($dir,$mode);
}

function read_groups(){
  global $GROUPS;
  if(!$gfile = fopen(AUTH_GROUP_FILE, "r")) return false;
  while(!feof($gfile)){
    $line = fgets($gfile);
    list($group, $users) = explode(':', $line);
    $group = trim($group);
    if($group != ''){
      $user_list = explode(' ', $users);
      foreach($user_list as $user)
        $GROUPS[$group][] = trim($user);
    }
  }
  fclose($gfile);
  # patched by bitl
  //if(!$GROUPS) return false;
  //return true;
  return $GROUPS;
}

function face_control($service){
	
	global $USER_ID, $USER_NAME, $USER_LOGIN;

	if ($uid = authenticate()) {

		$tmp = db_get("select * from core.users where id='$uid'");
		$USER_LOGIN = $tmp[0]['login'];
		$USER_NAME = $tmp[0]['name'];

		if (authorize($uid, $service)) {
			return $uid;
		} else {
			header("Location: login.php?do=auth-norights");
		};

	} else {
		header("Location: login.php?do=auth-unauthed");
	};

}

function authenticate() {

	global $USER_ID;

	// Try password based authentication first
	if (isset($_REQUEST['authlogin']) and isset($_REQUEST['authpasswd']))  {

		$login = db_escape($_REQUEST['authlogin']);
		$passwd = db_escape($_REQUEST['authpasswd']);

		// Try to authenticate user
		if ($data = db_get("select core.auth_passwd('$login', '$passwd') as uid")) {
			if ($uid = $data[0]['uid']) {
				$USER_ID = $uid;
				$tmp = db_get("select core.create_session('$uid',864000) as key");
				$session_key = $tmp[0]['key'];
				setcookie('SESSID', $session_key);
				return $uid;
			};
		};
		
		return false;
	
	};

	// Try session based authentication
	if ($_COOKIE['SESSID']) {
		
		$key = db_escape($_COOKIE['SESSID'].'');

		if ($data = db_get("select core.auth_session('$key') as uid")) {
			if ($uid = $data[0]['uid']) {
				$USER_ID = $uid;
				db("select core.update_session('$key', 864000)");
				return $uid;
			};
		};

	};

	return false;

}

function authorize($uid, $service) {

	$data = db_get("select core.authorize($uid,'$service','access')::integer as allowed");
	if ($data[0]['allowed']) {
		return true;
	} else {
		return false;
	}
}


function access_granted($group){
  global $GROUPS;
  if(!$GROUPS) if(!read_groups()) return false;
  # patched by bitl
  return in_array($_SERVER['REMOTE_USER'], $GROUPS[$group]);
}


function app_config($app_id){
  $config = array();
  if($data = db_get("select tag, value from core.apps_conf where app_id = $app_id"))
    foreach($data as $row)
      if(isset($config[$row['tag']]))
        if(is_array($config[$row['tag']]))
          array_push($config[$row['tag']],$row['value']);
        else
          $config[$row['tag']] = array($config[$row['tag']], $row['value']);
      else
        $config[$row['tag']] = $row['value'];
  return $config;
}

/////////////////////////////////////
//
// converts '380677025725' to '+380 67 702-57-25'
//

function format_msisdn( $msisdn, $plus = true, $minuses = true ){
        $sep = $minuses ? "-" : " ";
        return ( $plus ? "+" : "" )
                . substr( $msisdn, 0, 3 ) ." ". substr( $msisdn, 3, 2 ) ." "
                . substr( $msisdn, 5, 3 ) .$sep. substr( $msisdn, 8, 2 ) .$sep
                . substr( $msisdn, 10, 2 );
}

////////////////////////////////////
//
// errors handling
//

function logAndDie($string) {
  $trace=debug_backtrace();
  $file=$trace[0]['file'];
  if(strpos($file, SYS)===0) $file=substr($file, strlen(SYS));
  $where=$file.'('.$trace[1]['line'].')::'.$trace[1]['function'].'("'.implode('", "',$trace[1]['args']).'")';
  //$now = date("D M j G:i:s T Y");
  $now = date ("Y M d H:i:s");
  error_log("$now [$where] $string\n", 3, LOG_FILE);
  die('log\'n\'die');
}

function warn($string){
  $trace=debug_backtrace();
  $file=$trace[0]['file'];
  if(strpos($file, SYS)===0) $file=substr($file, strlen(SYS));
  $where=$file.'('.$trace[1]['line'].')::'.$trace[1]['function'].'("'.implode('", "',$trace[1]['args']).'")';
  trigger_error("{<br>&nbsp;&nbsp;&nbsp;&nbsp;$string<br>&nbsp;&nbsp;&nbsp;&nbsp;at $where<br>}",E_USER_WARNING);
}

function error($string){
  $trace=debug_backtrace();
  $file=$trace[0]['file'];
  if(strpos($file, SYS)===0) $file=substr($file, strlen(SYS));
  $where=$file.'('.$trace[1]['line'].')::'.$trace[1]['function'].'("'.implode('", "',$trace[1]['args']).'")';
  trigger_error("{<br>&nbsp;&nbsp;&nbsp;&nbsp;$string<br>&nbsp;&nbsp;&nbsp;&nbsp;at $where<br>}",E_USER_ERROR);
}

function make_main_menu () {

	$menu = '';

	$data = db_get("select * from core.services where visible = true and uri not in ('', 'internal') order by id desc");
	foreach ($data as $row) {
		$menu .= ' <a href="'.$row['uri'].'">'.translate("cms_head_".$row['service']).'</a> |';
	};

	return $menu;

}


