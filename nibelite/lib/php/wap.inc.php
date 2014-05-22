<?php
/* ===========================================================================
 *
 * SVN: $Id: wap.inc.php 159 2008-02-19 11:20:55Z misha $
 *
 * MODULE
 * 	
 * 	wap.inc.php
 *
 * DESCRIPTION
 *
 * 	This module contains some useful functionality for WAP services development
 * 	like MNO and MSISDN determination, handset model determination, WAP page
 * 	rendering, direct HTTP download implementation.
 *
 * AUTHORS
 *
 *	Anatoly Matyakh <zmeuka@x-play.com.ua>
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 *	1. Common Nibelite functionality (core.inc.php, pgsql.inc.php)
 *
 * TODO
 *
 *	1. Implement Wono WAP Proxy support.
 *	2. Refactor, refactor, refactor.
 * 
 * ===========================================================================
 */


// List of file extensions by type
$EXTS = array(
	'application/vnd.smaf'             => 'mmf',
	'audio/imelody'                    => 'imy',
	'audio/midi'                       => 'mid',
	'audio/vnd.nok-ringingtone'        => 'nok',
	'image/gif'                        => 'gif',
	'image/jpeg'                       => 'jpg',
	'image/png'                        => 'png',
	'image/vnd.wap.wbmp'               => 'wbmp',
	'application/vnd.alcatel.seq'      => 'seq',
	'text/vnd.sun.j2me.app-descriptor' => 'jad',
	'application/java'                 => 'jar',
	'audio/amr-wb'                     => 'awb',
	'audio/amr'                        => 'amr',
	'audio/wav'                        => 'wav',
	'audio/mpeg'                       => 'mp3',
	'audio/mp3'                        => 'mp3',
	'video/3gpp'                       => '3gp',
	'video/mp4'                        => 'mp4',
);

// Types by extensions list (reverse to $EXTS) 
$TYPES = array();
foreach($EXTS as $k=>$v) $TYPES[$v] = $k;

// Markups and MIME types.
$MARKUPS = array(
	'wml'   => 'text/vnd.wap.wml;charset=utf-8',
	'html'  => 'text/html;charset=utf-8',
	'xhtml' => 'application/xhtml+xml;charset=utf-8'
);



/* ===========================================================================
 * Function: render($data)
 * Parameters: data (scalar, array or object)
 * Return: none
 *
 * Render any data and send to stdout.
 * - scalars are printed as is;
 * - arrays are rendered sequentially;
 * - for objects render() method is called.
 */
function render($data) {

	// If scalar, simply print to stdout.
	if (is_scalar($data)) {
		echo $data;
	};

	// If array, render all items.
	if (is_array($data)&&sizeof($data)) {
		foreach($data as $item) {
			render($item);
		};
	};

	// If object with render method, call this method.
	if(is_object($data) && method_exists($data,"render")) {
		$data->render();
	};

} // function render($data)



/* ===========================================================================
 * Class: wapsession
 *
 * It's base WAP class used in Nibelite WAP services.
 *
 * $wap = new wapsession();
 */
class wapsession {

var $remote_ip; // Remote IP address
var $mno; // Mobile operator mnemonic name
var $msisdn; // Subscriber's fully qualified MSISDN (international format without leading +)
var $accept; 
var $trusted; // If 1 - trusted authentication from MNO, if 0 - some dirty hacks
var $log; 
var $config; // Configuration of Nibelite WAP framework
var $markup;
var $log_details;
var $model_id; // WAP terminal model ID (see tables models, brands)
var $wurfl; // WURFL record for session
  


/* ===========================================================================
 * Class constructor: wapsession($app_id)
 *
 * Initialize WAP session parameters.
 *
 */
function wapsession($app_id = 3) {

	$this->get_mno();
	$this->get_msisdn();
	//$this->get_accept();
	//$this->log($_SERVER['REQUEST_URI']." : ".$this->remote_ip);
	$this->get_config($app_id);
	//$this->get_wurfl();

} // function wapsession($app_id = 3)



/* ===========================================================================
 *
 */
function log($str) {

	if (!$this->log) $this->log = fopen(SYS.'/log/waplog','a');
	if ($this->log) fputs($this->log, date ("Y M d H:i:s")." {".getmypid()."} ".$this->msisdn.'/'.$this->trusted." $str\n");

} // function log($str)
 


/* ===========================================================================
 *
 */
function log_details($fname='waplog_details') {

	if (!$this->details_log) {
		$this->details_log = fopen(SYS.'/log/'.$fname, 'a');
	};

	if ($this->details_log) {
		fputs($this->details_log, "=== [" . date ("Y M d H:i:s")." {".getmypid()."} "
			.$this->msisdn.'/'.$this->trusted. "] =======================\n");
		fputs($this->details_log, '$_SERVER === '.print_r($_SERVER, true)."\n");
		fputs($this->details_log, "\n");
	};

} //function log_details($fname='waplog_details')
 


/* ===========================================================================
 *
 */
function get_wurfl() {

	$user_agent = db_escape($_SERVER['HTTP_USER_AGENT']);

	$this->wurfl = array();

	if(preg_match('/^([^\/\s]+)\/.*/', $user_agent, $parts)) {
		$user_agent = $parts[1];
	};

	$sql = "select * from wurfl where user_agent='$user_agent'";

	if ($data = db_get($sql)) {
		foreach ($data[0] as $cap => $val) {
			$this->wurfl[$cap] = $val;
		};
	};

} // function get_wurfl() {



/* ===========================================================================
 * Function: get_config
 * Reads configuration from DBMS and put it to object property ($this->config).
 */
function get_config($app_id) {

	$this->config = array();

	// Fetch configuration from database.
	if ($data = db_get("select tag, value from core.apps_conf where app_id = $app_id")) {

		foreach($data as $row) {

			// If olready exists such config key - make it an array.
			if(isset($this->config[$row['key']])) {

				// If already array field - ok, othervise - create new one with two items.
				if(is_array($this->config[$row['key']])) {
					array_push($this->config[$row['key']],$row['value']);
				} else {
					$this->config[$row['key']] = array($this->config[$row['key']], $row['value']);
				};

			// If new config key - simply add new config item to object.
			} else {
				$this->config[$row['key']] = $row['value'];
			};

		}; // foreach($data as $row

	};

} // function get_config($app_id)
  


/* ===========================================================================
 *
 */ 
function close() {

	if($this->log) fclose($this->log);

} // function close()



/* ===========================================================================
 * Funciotn: get_mno()
 * Set mno property to WAP session by remote client IP address.
 * Allowed names: kyivstar, mts, life, beeline
 *
 * TODO:
 * - move IP networks to configuration file;
 * - implement proxy headers checking.
 */
function get_mno() {

	$remote_ip = getenv('REMOTE_ADDR');
	$this->remote_ip = $remote_ip;
		
	$this->mno = 'unknown';

	// Check WAP Proxy HTTP headers
	if (preg_match('/^192\.168\./', $remote_ip)) { $this->mno = 'local'; }
	if (preg_match('/^127\.0\./', $remote_ip)) { $this->mno = 'local'; }

	// Kyivstar GSM (AceBase, DJUICE, Mobilych)
	if (preg_match('/^193\.41\.60\./', $remote_ip)) { $this->mno = 'kyivstar'; }
	if (preg_match('/^81\.23\.22\.25[1234]/', $remote_ip)) { $this->mno = 'kyivstar'; }

	// MTS Ukraine (MTS, UMC, JEANS, Ekotel)
	if (preg_match('/^80\.255\.64\./', $remote_ip)) { $this->mno = 'mts'; }

	// Astelit (Life)
	if (preg_match('/^212\.58\.162\.23[0123]/', $remote_ip)) { $this->mno = 'life'; }

	// Ukrainian Radio Systems (Beeline)
	if (preg_match('/^193\.239\.128\./', $remote_ip)) { $this->mno = 'beeline'; }

	// VELCOM WAP
	if (preg_match('/^212\.98\.178\./', $remote_ip)) { $this->mno = 'velcom'; }

} // function get_mno()



/* ===========================================================================
 * Funciotn: get_msisdn()
 */
function get_msisdn() {

	$this->msisdn = '';
	$this->trusted = 0;

	// Check UMC requests (X-Network-Info HTTP header)
	if($this->mno == 'mts') {
	  $m = array();
	  if (preg_match('/(\d{7,})/',$_SERVER['HTTP_X_NETWORK_INFO'],$m)) {
			$this->msisdn = trim($m[1]);
		}
	}
	
	// Check Kyivstar requests (User-Identity-Forward-msisdn HTTP cookie)
	if (($this->mno == 'kyivstar') or ($this->mno == 'velcom')) {
	  //if (isset($_COOKIE['User-Identity-Forward-msisdn'])){
	  $msisdn = $_COOKIE['User-Identity-Forward-msisdn'];
	  if ( preg_match( "/^(380[0-9]{9})/", $msisdn) ) {
			$this->msisdn = $msisdn;
	  } else {
			$this->msisdn  = "380". preg_replace( '/3(\d)3(\d)3(\d)3(\d)3(\d)3(\d)3(\d)3(\d)3(\d)(.*)/', '$1$2$3$4$5$6$7$8$9', $msisdn);
	  }
	};

	// Astelit X-MSISDN header processing
	if($this->mno == 'life') {
		$m = array();
		if (preg_match('/(\d{7,})/',$_SERVER['HTTP_X_MSISDN'],$m)) {
			$this->msisdn = trim($m[1]);
		};
	};


	// FIXME: This is too buggy
	// Ok, we'll trust ?a=... even if redirected :)
	if (isset($_SERVER['REDIRECT_QUERY_STRING'])) {
		parse_str($_SERVER['REDIRECT_QUERY_STRING'],$qry);
		$_REQUEST['a'] = $qry['a'];
	};

	// FIXME: This is too buggy
	if($this->msisdn!='') {
		if(substr($this->msisdn,0,3)!='380') {
			$this->msisdn = '380'.$this->msisdn;
		}
		$this->trusted = 1;
	} else {
	  $this->msisdn = trim($_REQUEST['a']);
  };

} // function get_msisdn()



/* ===========================================================================
 * Function: auth_by_session($session_id)
 *
 * Implements session based authentication.
 *
 */
function auth_by_session($session_id) {

	if ($tmp = db_get("select * from requests where session_id = '$session_id' order by id desc limit 1")) {
		$this->msisdn = $tmp[0]['msisdn'];
	};

} // function auth_by_session($session_id)



/* ===========================================================================
 * Function: get_accept()
 *
 * Determine the following information:
 * - markup: by Accept, HTTP parameters, data from accept table;
 * - accepted MIME types list.
 */
function get_accept() {

	global $MARKUPS, $CONFIG;

	// ----------- ACCEPT -----------

	$this->accept = array();
	$accept_str = $_SERVER['HTTP_ACCEPT'];

	// Redefining Accept-List if we know this device.
	if ($data = db_get("select agent,accept from accept order by agent")) {
		foreach ($data as $rec) {
			if (@preg_match('/'.trim($rec['agent']).'/i',$_SERVER['HTTP_USER_AGENT'])) {
				$accept_str = $rec['accept'];
			};
		};
	};

	// Fill accepted MIME types list of object.
	$accept_str = strtolower($accept_str);
	$acc = explode(',',$accept_str);
	foreach($acc as $val) {
		$this->accept[trim($val)] = 1;
	};

	// ----------- MARKUP -----------

	$this->markup = $CONFIG['wap']['default_markup'];

	// Try to get markup by 'm' request parameter.
	if(	$_REQUEST['m'] ){

		// If known markup, set it by request parameter
		if ( $MARKUPS[$_REQUEST['m']] ) {
			$this->markup = $_REQUEST['m'];
		};

	// Otherwise try to set 
	} elseif ($this->accept['application/vnd.wap.xhtml+xml'] or $this->accept['application/xhtml+xml'] ) {
	  $this->markup = 'xhtml';
	}

	// Misha: never show XHTML for SE T610
	if (
			(preg_match('/SonyEricssonT610/', $_SERVER['HTTP_USER_AGENT']))
			or (preg_match('/SonyEricssonT230/', $_SERVER['HTTP_USER_AGENT']))
			) {
		$this->markup = 'wml';
		}

} // function get_accept()



/* ===========================================================================
 * Function: save_agent()
 *
 * Store User-Agent, Accept, X-WAP-Profile, X-WAP-Profile-Diff headers to DB.
 *
 * See tabls agents for details.
 */
function save_agent() {

	$agent = db_escape(trim($_SERVER['HTTP_USER_AGENT']));
	$accept = db_escape(trim($_SERVER['HTTP_ACCEPT']));
	$profile = db_escape(trim($_SERVER['HTTP_X_WAP_PROFILE']));
	$diff = db_escape(trim($_SERVER['HTTP_X_WAP_PROFILE_DIFF']));

	// If we already know this device, update popularity.
	if($data = db_get("select id, pop from agents where agent='$agent'")){
	  $id = $data[0]['id'];
	  db("update agents set pop=pop+1 where id=$id");
	// Otherwise insert information to DB.
	}else{
	  db("insert into agents(agent,accept,profile,diff,pop) values('$agent','$accept','$profile','$diff',1)");
	};

} // function save_agent()



/* ===========================================================================
 * Function: detect_model()
 *
 * Returns phone model ID from table public.models by User-Agent HTTP header.
 */
function detect_model() {

	global $DEBUG;

	// First we try to get ID from object properties.
	// If not detected yet - call internal method _detect_model().
	$model_id = isset($this->model_id) ? $this->model_id : ($this->model_id = $this->_detect_model());

	// Saving debug info about model detection
	if ($DEBUG) {
		if ($model_id) {
			$this->log("[DEBUG] detect_model() => $model_id");
		} else {
			$this->log("[DEBUG] detect_model() => UNKNOWN : UA [".$_SERVER['HTTP_USER_AGENT']."]");
		};
	};

	return $model_id;

} // function detect_model()



/* ===========================================================================
 * 
 */ 
function _detect_model() {

	global $DEBUG;

	$ua = $_SERVER['HTTP_USER_AGENT'];

	$bmq = "select m.id from public.models m, public.brands b where m.brand_id=b.id and b.name='%s' and m.name='%s' limit 1";

//	if(preg_match('/^Mozilla.*Opera/',$ua))
//		return true;
//	$ua = "SonyEricssonW800i/R1N Browser/SEMC-Browser/4.2 Profile/MIDP-2.0 Configuration/CLDC-1.1";

	// Ignore Mozilla like web browsers except Symbian port.
	if(preg_match('/^Mozilla/',$ua) and !preg_match('/Symbian/',$ua)) {
		return 0;
	};

	// Ignore non mobile Opera
	if(preg_match('/^Opera/',$ua) and !preg_match('/Opera Mini/',$ua)) {
		return 0;
	};

	// Modify User-Agent for some Sony Ericsson W800i
	if(preg_match('/^Mozilla.*Opera/',$ua))
	{
		$ua = "SonyEricssonW800i/R1N Browser/SEMC-Browser/4.2 Profile/MIDP-2.0 Configuration/CLDC-1.1";
	};

	// If no User-Agent provided, try other HTTP headers.
	// Possible LG stuff, try UAProf information.
	if (!$ua) {
		if (preg_match('#LG-(.*)\.xml$#', $_SERVER['HTTP_X_WAP_PROFILE'], $p)) {
			$data = db_get(sprintf($bmq, 'LG', $p[1]));
			if ($data) {
				return $data[0]['id'];
			};
		};
	};

	if(strpos($ua, 'es61i') != false) {
		$data = db_get(sprintf($bmq, 'Nokia', 'E61'));
		if ($data) return $data[0]['id'];
	}
	if(strpos($ua, 'SonyEricssonT68/R201A Profile/MIDP-1.0')!==false) {
    	    // у настоящего SE T68 джавы нету. значит, это LG G1600 маскируется
	    $data = db_get(sprintf($bmq, 'LG', 'G1600'));
	    if ($data) return $data[0]['id'];
	}
    
	$data = db_get("select distinct model_id from public.useragents where agent='{$ua}'");
	// if (count($data)>1) ...	  
	if ($data) return $data[0]['model_id'];
	$short_ua = preg_replace('/(\/| ).*$/', '', $ua);
	$data = db_get("select distinct model_id from public.useragents where agent like '{$short_ua}%'");
	// if (count($data)>1) ...	  
	if ($data) return $data[0]['model_id'];
	$data = db_get("select distinct model_id from public.useragents where agent like '%{$short_ua}%'");
	// if (count($data)>1) ...	  
	if ($data) return $data[0]['model_id'];

	/* LG */
        if (preg_match('/^LG(E-|\/|-| )?(\w*)($| |\/)/', $ua, $p)) {
    	    if (preg_match('/^\d{4}$/', $p[2])) $p[2]='G'.$p[2];
	    $data = db_get(sprintf($bmq, 'LG', $p[2]));
	    if ($data) return $data[0]['id'];
	    $this->log('unknown LG model: ' . $p[2]);
	}

	/* Samsung */
	if (preg_match('/^(SAMSUNG[- ]{1,2}|Samsung-|SEC-)?SGH-?(\w*)($| |\/|,|-)/', $ua, $p)) {
	    if (preg_match('/[AG]$/', $p[2])) $p[2]=substr($p[2], 0, strlen($p[2])-1);
	    $data = db_get(sprintf($bmq, 'Samsung', $p[2]));
	    if ($data) return $data[0]['id'];
	    $p24 = substr($p[2], 0, 4); // X640C -> X640
	    $data = db_get(sprintf($bmq, 'Samsung', $p24));
	    if ($data) {
		$this->log('rough Samsung model detection: ' . $p[2] . ' -> '. $p24);
		return $data[0]['id'];
	    }
	    if ($p24[3]!='0') { // (C108T ->) C108 -> C100, X461 -> X460
		$p24[3]=0;
		$data = db_get(sprintf($bmq, 'Samsung', $p24));
		if ($data) {
		    $this->log('very rough Samsung model detection: ' . $p[2] . ' -> '. $p24);
		    return $data[0]['id'];
		}
	    }
	    $this->log('unknown Samsung model: ' . $p[2]);	    
	}

	// Return false result if nothing found.
	return 0;

}


/* ===========================================================================
 * Function: page($)
 *
 */

function page($page) {

	$document = new wml();
	$document->markup = $this->markup;

	if(isset($page['content'])){
	  if($page['id']) $id = $page['id']; else $id = 'page';
	  if($page['title']) $title = $page['title']; else $title = 'WAP';
	  if($page['content']) $content = $page['content']; else $content = 'Empty page';
	  $card = new card($id,$title,$content);
	  $document->add($card);
	}else{
	  foreach($page as $row){
		if($row['id']) $id = $row['id']; else $id = 'page';
		if($row['title']) $title = $row['title']; else $title = 'x-play';
		if($row['content']) $content = $row['content']; else $content = 'Empty page';
		$card = new card($id,$title,$content);
		$document->add($card);
	  }
	}
	if(!$document->cards){
	  $card = new card('card1','MTV','No Cards defined');
	  $document->add($card);
	}

	$document->render();

} // function page($page)



/* ===========================================================================
 * Function: display($content, [$utf])
 *
 * Sending given content to client with possible recoding (CP1251 => UTF-8).
 */
function display($content, $utf = true) {

	global $MARKUPS;

	if($utf) {
		$content = iconv("CP1251","UTF-8//IGNORE",$content);
	}
	print $content;

} // function display($content, $utf = true)



/* ===========================================================================
 * Function: header()
 *
 * Send common WAP HTTP headers to clients.
 */
function header() {

	global $MARKUPS;

	// First we send common HTTP headers
	header("HTTP/1.1 200 OK");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache");
	header("Pragma: no-cache");
	header("ETag: ".md5(gmdate("D, d M Y H:i:s")." GMT"));

	// If we know markup, send Content-Type header here.
	if(isset($MARKUPS[$this->markup])) {
		header("Content-Type: ".$MARKUPS[$this->markup]);
	};

} // function header()



/* ===========================================================================
 * Functio: give($content,$mime,$filename='')
 *
 * Implements direct HTTP download protocol.
 */
function give($content,$mime,$filename='') {

	$etag = md5($content);
	$size = strlen($content);

	// If Range header present, implement chunked transfer.
	if (isset($_SERVER['HTTP_RANGE'])) {

	  list($units,$val) = explode('=', $_SERVER['HTTP_RANGE']);
	  list($start,$end) = explode('-', trim($val));
	  $start = trim($start) + 0;
	  $end = trim($end) +0;

		if (!$end or ($end>=$size)) {
			$end = $size-1;
		};

		$chunksize = $end - $start + 1; 
		header("HTTP/1.1 206 Partial Content");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		header("ETag: $etag");
		header("Accept-Ranges: bytes");
		header("Content-Length: $chunksize");
		header("Content-Range: bytes {$start}-{$end}/{$size}");
		header("Content-Type: $mime");
		echo substr($content,$start,$chunksize);

	}else{

		header("HTTP/1.1 200 OK");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		header("ETag: $etag");
		header("Accept-Ranges: bytes");
		header("Content-Length: ".strlen($content));
		header("Content-Type: $mime");
		print $content;

	};

} // function give($content,$mime,$filename='')
 

} // class wapsession



/*
 *
 */
class xhtml {
	var $cards=array();

	function add($card) {
	    $this->cards[]=$card;
	}

	function render() {
		header("HTTP/1.1 200 OK");
	    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	    header("Cache-Control: no-cache");
	    header("Pragma: no-cache");
	    header("ETag: ".md5(gmdate("D, d M Y H:i:s")." GMT"));
	    header("Content-Type: application/xhtml+xml;charset=utf-8");
	    echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<!DOCTYPE html PUBLIC " .
			"\"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" ".
			"\"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n".
			"<html xmlns=\"http://www.w3.org/1999/xhtml\">\n".
			"<head>\n".
			"	<title>WapCart</title>\n".
			"</head>\n";
		echo "<body>\n";
	    render($this->cards);
	    echo "</body>\n</html>\n";
	}
} // class xhtml



/*
 *
 */
class wml {
	var $cards=array();
	var $markup = "wml";

	function add($card) {
		$card->markup = $this->markup;
	    $this->cards[]=$card;
	}

	function render() {

		if ($this->markup == 'wml') {
			header("HTTP/1.1 200 OK");
		    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		    header("Cache-Control: no-cache");
		    header("Pragma: no-cache");
		    header("ETag: ".md5(gmdate("D, d M Y H:i:s")." GMT"));
		    header("Content-Type: text/vnd.wap.wml;charset=utf-8");
		    echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		    echo "<!DOCTYPE wml\n PUBLIC ".
				"\"-//WAPFORUM//DTD WML 1.1//EN\" ".
				"\"http://www.wapforum.org/DTD/wml_1.1.xml\">\n";
		    echo "<wml>\n";
		    render($this->cards);
		    echo "</wml>\n";
		} elseif ($this->markup == 'xhtml') {
			header("HTTP/1.1 200 OK");
		    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		    header("Cache-Control: no-cache");
		    header("Pragma: no-cache");
		    header("ETag: ".md5(gmdate("D, d M Y H:i:s")." GMT"));
		    header("Content-Type: application/xhtml+xml;charset=utf-8");
		    echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
			echo "<!DOCTYPE html PUBLIC " .
				"\"-//WAPFORUM//DTD XHTML Mobile 1.0//EN\" ".
				"\"http://www.wapforum.org/DTD/xhtml-mobile10.dtd\">\n".
				"<html xmlns=\"http://www.w3.org/1999/xhtml\">\n".
				"<head>\n".
				"	<title>WapCart</title>\n".
				"</head>\n";
			echo "<body>\n";
		    render($this->cards);
		    echo "</body>\n</html>\n";
		};
	}
} // class wml



/* ===========================================================================
 * Class: card
 *
 * Common class implementing API for simple WML cards.
 */
class card {

var $id;             // card id
var $title;          // card title
var $content;        // card content
var $markup = "wml"; // markup (WML of course)

function card($id,$title,$content='') {
	$this->id=iconv("CP1251","UTF-8//IGNORE",$id);
	$this->title=iconv("CP1251","UTF-8//IGNORE",$title);
	$this->content=iconv("CP1251","UTF-8//IGNORE",$content);
} // function card($id,$title,$content='')

function render() {
	if ($this->markup == 'wml') {
		echo "\t<card id=\"".$this->id."\" title=\"".	htmlspecialchars($this->title)."\">\n";
		render($this->content);
		echo "\t</card>\n\n";
	} elseif ($this->markup == 'xhtml') {
		echo "\t<p id=\"".$this->id."\">\n<h1>". htmlspecialchars($this->title)."</h1>\n";
		render($this->content);
		echo "\t</p>\n\n";
	};

} // function render()

} // class card

?>
