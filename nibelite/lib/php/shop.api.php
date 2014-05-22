<?php
/* ===========================================================================
 *
 * SVN: $Id: shop.api.php 159 2008-02-19 11:20:55Z misha $
 *
 * MODULE
 * 	
 * 	code_template.inc.php
 *
 * DESCRIPTION
 *
 * 	This module contains code template for new modules.
 *
 * AUTHORS
 *
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 *	1. http://www.php.net/
 *
 * TODO
 *
 *	1. Implement something :)
 * 
 * ===========================================================================
 */
//
// ��������� �������� �������� (���-�������, ���-�������)
// � ������ �� ���: �������� ����� <zmeuka@x-play.com.ua>
//
//

require_once('core.inc.php');
require_once('wap.inc.php');
require_once('ss.api.php');

if (!defined("CATALOG_PID_DEFAULT")) {
	define("CATALOG_PID_DEFAULT",1);
};
define("PARTNER_ID_DEFAULT",'101');

// ����� ContentShop
// ��������� ����� ������ �������� �������������.

class ContentShop {

var $wap;          // ������ wapsession
var $partner;      // ��� ����Σ��
var $cat_id;       // ID ������� �������� 
var $item_id;      // ID ������� ��������
var $model_id;     // �������������� ��� ��������� ������ ��������
var $msisdn;       // �������������� (?) ����� ��������
//  var $channels; // ���������� ������ ������
var $root_level;
    
// HTTP
// ���� ������ ����� ������������ 404, ��������� $_SERVER['REQUEST_URI']
// ��������: /wap/shop/042?model=312
//		����� � ���� ����� ����������� ��� ����� - ����Σ�, ������� � �������
//		������ � ����� �������
var $get;			// ������ $_GET ��� ������������� ������������ 404
    

// =========================================================================
// �����������
// =========================================================================
// �������� ���������� �ӣ
function ContentShop ( $wap = false, $init = false ) {

	global $DBH;

	$this->root_level = -1;
	$request = '';
    	
	if (isset($wap) && is_object($wap)) {
		$this->wap = $wap;
	};

	// Set some object properties manually from $init parameter.
	if ($init && is_array($init)) {
		if(isset($init['root_level'])) { $this->root_level = $init['root_level']+0; };
		if(isset($init['partner']))    { $this->partner    = $init['partner']; };
		if(isset($init['cat_id']))     { $this->cat_id     = $init['cat_id']+0; };
		if(isset($init['item_id']))    { $this->item_id    = $init['item_id']+0; };
		if(isset($init['model_id']))   { $this->model_id   = $init['model_id']+0; };
		if(isset($init['msisdn']))     { $this->msisdn     = $init['msisdn']; };
		if(isset($init['request']))    { $request          = $init['request']; };
	};

	// Detect some WAP properties if not yet set.
	if (isset($this->wap)) {
		if (!isset($this->msisdn)) { $this->msisdn	= $this->wap->msisdn; };
		if (!isset($this->model_id)) { $this->model_id	= $this->wap->detect_model();	};
	};
    	
	// Detect available sales channels for this MSISDN.
	// $this->channels = $this->getChannels($this->msisdn);

	// Parse HTTP request from WAP client.
	$this->parseRequest($this->root_level,$request);

	// Set default values if not yet set or determined automatically.
	if (!$this->cat_id)  { $this->cat_id  = CATALOG_PID_DEFAULT; };
	if (!$this->partner) { $this->partner = PARTNER_ID_DEFAULT; };
    	
	// Initialize compatibility system.
	SS::init($DBH);

} // function ContentShop ( $wap = false, $init = false )



	// =========================================================================
	// ������
	// =========================================================================
	
// isDir() - ���������� TRUE, ���� �� ������ �������� ������ ����������� ��������
function isDir() {

	return ($this->item_id) ? false : true;	// ���� toBoolean+Not

} // function isDir()



// isItem() - ���������� TRUE, ���� �� ������ �������� �������� ����������� �������
function isItem() {

		return ($this->item_id) ? true : false;	// ���� toBoolean

} // function isItem()

	// getSubDirs() - ���������� ������ �������� ������������ ������� ����� 
	//		� ���� ������ �������� Catalog (��� cat_id, ���� $obj == 0)
	function getSubDirs($obj = 1){
		if ($this->cat_id == 1) {
		    	return SS::getLinks($this->cat_id,0,$obj);
		} else {
    			return SS::getLinks($this->cat_id,$this->model_id,$obj);
		};
	}

	// getTopSaleItems () - ���������� ������ ����� ����������� �������� ������� �����
	// 		� ���� ������ �������� Content (��� content_id, ���� $obj == 0)
	function getTopSaleItems($obj = 1) {
			return false;
	}
	// getItems() - ���������� ������ ����������� �������� ������� �����
	//		� ���� ������ �������� Content (��� content_id, ���� $obj == 0)
	function getItems($obj = 1){
		return SS::getContent($this->cat_id,$this->model_id,$obj);
	}

	// getSubItems() - ���������� ������ ����������� �������� �������
	// 		� ���� �������� ����� � ���� ������ �������� Content 
	// 		(��� content_id, ���� $obj == 0)
	function getSubItems($obj = 1){
		return SS::getContent($this->cat_id,$this->model_id,$obj, true);
	}
	
	// getItem() - ���������� ������ Content
	function getItem(){
		global $DBH;
		if($this->isItem()){
			return new Content($DBH, $this->item_id);
		}else
			return false;
	}
	

// getDir() - ���������� ������ Catalog
function getDir() {

	global $DBH;
	return new Catalog($DBH, $this->cat_id);

} // function getDir()
	
	// getLink( $root_prefix, $cat_id, $item_id ) - ���������� HTTP-������
	function getLink( $root_prefix='', $cat_id=0, $item_id=0 ){
		$link = $root_prefix;
		$link .= '/'.$this->partner;
		if(!$cat_id) $cat_id = $this->cat_id;
		$link .= '/'.$cat_id;
		if($item_id) $link .= '/'.$item_id;
		return $link;
	}

	// getCodes($Content) - �������� ������ Content, ���������� ������ ���� ������
	function getCodes( $Content ){
		if($Content){
			$channels = $this->getChannels($this->msisdn);
			$codes = $Content->getCodeList();
			if(!($codes && $channels)) return array();
			$retcodes = array();
			foreach($codes as $row)
				if($channels[$row['channel']])
					if(isset($retcodes[$row['code']])){
						if($channels[$retcodes[$row['code']]]['priority'] > $channels[$row['channel']]['priority'])
							$retcodes[$row['code']] = $row['channel'];
					}else
						$retcodes[$row['code']] = $row['channel'];
			
			// ������ � $retcodes ���������� ���� $code=>$channel
			// ����� ����������, ���� �� �������� �� ��������
			$formats = array();
			$datalist = $Content->getDataList();
			$legend = $this->getLegend();
			foreach($datalist as $key)
				if(isset($legend[$key]))
					$formats[$key] = $legend[$key];
			if(!$formats)
				$formats['wap'] = '';
				
			// ������ �������� �ӣ � ����
			$kucha = array();
			foreach($retcodes as $code=>$channel){
				$op = $channels[$channel]['operator'];
				$num = $channels[$channel]['number'].':'.$channels[$channel]['price'];
				foreach($formats as $format=>$digit){
					if(preg_match('/sms\.ems/',$format)) $format = 'ems';
					elseif(preg_match('/sms\.siemens/',$format)) $format = 'siemens';
					elseif(preg_match('/sms\.nokia/',$format)) $format = 'nokia';
					$kucha[$op][$num][$format] = $this->partner . $code . $digit;					
				}
			}
			return $kucha;	
		}else
			return false;
	}

	// =========================================================================
	// ��������������� �������
	// =========================================================================

	// ���������� ������ ������������ ����� ���� ������ � ������ ������
	function getLegend() {
		$legend = array();
		if($data = db_get("select type,code from rulesmap left join datatypes on datatypes.id=type_id"))
			foreach($data as $rec)
				$legend[$rec['type']] = $rec['code'];
		return $legend;
	} 

    // ���������� ������ �������, ���������� ������� ������ ��������
    // ������ ������: $channels[$app_id]['priority'=>$p, 'operator'=>$o, 'price'=>$m, 'number'=>$n]
    function getChannels($msisdn) {
    	$channels = array();
    	
    	# ���� ������ �����, ������ ������ ������ ������� ���������
    	if($msisdn){
    		if($data = db_get("select app_id, value from apps_conf where key='msisdn'"))
    			foreach($data as $row)
    				if(preg_match('/'.$row['value'].'/', $msisdn))
    					$channels[$row['app_id']] = array();
		}
		
		# ���� �� ����� ������ (����� �� ������ ��� �� ����� �� �����), ������ ��� ������
		
		if(!$channels){
			if($data = db_get("select id from apps where active=1 and name like 'channel%'"))
				foreach($data as $row)
					$channels[$row['id']] = array();
		}
    	
    	# �������� ������ ������ � �������
    	
    	if($channels)
			foreach(array_keys($channels) as $app){
				$channels[$app]['priority'] = 9999;
				$channels[$app]['operator'] = 'unknown';
				$channels[$app]['price'] = 0;
				$channels[$app]['number'] = 'unknown';
				if($tmp = db_get("select value from apps_conf where key='priority' and app_id=$app"))
					$channels[$app]['priority'] = $tmp[0]['value'];
				if($tmp = db_get("select value from apps_conf where key='operator' and app_id=$app"))
					$channels[$app]['operator'] = $tmp[0]['value'];
				if($tmp = db_get("select value from apps_conf where key='price' and app_id=$app"))
					$channels[$app]['price'] = $tmp[0]['value'];
				if($tmp = db_get("select value from apps_conf where key='channel' and app_id=$app")){
					if(preg_match('/(\d+)$/',$tmp[0]['value'],$amatch))
						$number = $amatch[0];
					$channels[$app]['number'] = $number;
				}
			}

    	return $channels;
    }
    
// ��������� ����������� ���������
function parseRequest($root_level = -1, $request = '') {

	if ($request === '') {
		$request = $_SERVER['REQUEST_URI'];
	};

	// � ������� ����� ���� ������ ���� "?" - ����� ��� ��������� ����������
	list($path,$query) = explode('?',$request);
	$params = explode('/',$path);
	    
	if($root_level === -1) {
		// Need autodetection.
		for($i = 0; $i < count($params); $i++) {
			if(preg_match('/^\d+$/',$params[$i])) {
				$root_level = $i;
				break;
			};
		};
	};
	    
	// Set partner identifier.
	$this->partner = isset($params[$root_level+0]) ? $params[$root_level+0] : PARTNER_ID_DEFAULT;

	// Set catalog id.
	if(!isset($this->cat_id)) {
		$this->cat_id = isset($params[$root_level+1]) ? $params[$root_level+1]+0 : 0;
	};

	// Set item (content) id.
	$this->item_id = isset($params[$root_level+2]) ? $params[$root_level+2]+0 : 0;
    		
	if (!isset($this->get)) {
		$this->get = array();
		parse_str($query,$this->get); // parse as QUERY_STRING
	};

} // function parseRequest($root_level = -1, $request = '')


} // class ContentShop

?>
