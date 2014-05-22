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
// Интерфейс магазина контента (веб-каталог, вап-каталог)
// В ответе за это: Анатолий Матях <zmeuka@x-play.com.ua>
//
//

require_once('core.inc.php');
require_once('wap.inc.php');
require_once('ss.api.php');

if (!defined("CATALOG_PID_DEFAULT")) {
	define("CATALOG_PID_DEFAULT",1);
};
define("PARTNER_ID_DEFAULT",'101');

// Класс ContentShop
// экземпляр этого класса пассивно символизирует.

class ContentShop {

var $wap;          // Объект wapsession
var $partner;      // код партнёра
var $cat_id;       // ID раздела каталога 
var $item_id;      // ID единицы контента
var $model_id;     // определившаяся или выбранная модель телефона
var $msisdn;       // определившийся (?) номер телефона
//  var $channels; // подходящие каналы продаж
var $root_level;
    
// HTTP
// Если скрипт висит обработчиком 404, разбираем $_SERVER['REQUEST_URI']
// Например: /wap/shop/042?model=312
//		здесь в пути могут встретиться три числа - партнёр, каталог и контент
//		именно в таком порядке
var $get;			// Аналог $_GET при использовании обработчиком 404
    

// =========================================================================
// КОНСТРУКТОР
// =========================================================================
// пытается определить всё
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
	// МЕТОДЫ
	// =========================================================================
	
// isDir() - возвращает TRUE, если мы должны показать список продаваемых объектов
function isDir() {

	return ($this->item_id) ? false : true;	// типа toBoolean+Not

} // function isDir()



// isItem() - возвращает TRUE, если мы должны показать страницу конкретного объекта
function isItem() {

		return ($this->item_id) ? true : false;	// типа toBoolean

} // function isItem()

	// getSubDirs() - возвращает список активных подкаталогов текущей ветки 
	//		в виде списка объектов Catalog (или cat_id, если $obj == 0)
	function getSubDirs($obj = 1){
		if ($this->cat_id == 1) {
		    	return SS::getLinks($this->cat_id,0,$obj);
		} else {
    			return SS::getLinks($this->cat_id,$this->model_id,$obj);
		};
	}

	// getTopSaleItems () - возвращает список самых продаваемых объектов текущей ветки
	// 		в виде списка объектов Content (или content_id, если $obj == 0)
	function getTopSaleItems($obj = 1) {
			return false;
	}
	// getItems() - возвращает список продаваемых объектов текущей ветки
	//		в виде списка объектов Content (или content_id, если $obj == 0)
	function getItems($obj = 1){
		return SS::getContent($this->cat_id,$this->model_id,$obj);
	}

	// getSubItems() - возвращает список продаваемых объектов текущей
	// 		и всех дочерних веток в виде списка объектов Content 
	// 		(или content_id, если $obj == 0)
	function getSubItems($obj = 1){
		return SS::getContent($this->cat_id,$this->model_id,$obj, true);
	}
	
	// getItem() - возвращает объект Content
	function getItem(){
		global $DBH;
		if($this->isItem()){
			return new Content($DBH, $this->item_id);
		}else
			return false;
	}
	

// getDir() - возвращает объект Catalog
function getDir() {

	global $DBH;
	return new Catalog($DBH, $this->cat_id);

} // function getDir()
	
	// getLink( $root_prefix, $cat_id, $item_id ) - возвращает HTTP-ссылку
	function getLink( $root_prefix='', $cat_id=0, $item_id=0 ){
		$link = $root_prefix;
		$link .= '/'.$this->partner;
		if(!$cat_id) $cat_id = $this->cat_id;
		$link .= '/'.$cat_id;
		if($item_id) $link .= '/'.$item_id;
		return $link;
	}

	// getCodes($Content) - получает объект Content, возвращает полные коды заказа
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
			
			// Теперь в $retcodes содержатся пары $code=>$channel
			// Нужно посмотреть, есть ли вариации по форматам
			$formats = array();
			$datalist = $Content->getDataList();
			$legend = $this->getLegend();
			foreach($datalist as $key)
				if(isset($legend[$key]))
					$formats[$key] = $legend[$key];
			if(!$formats)
				$formats['wap'] = '';
				
			// Теперь собираем всё в кучу
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
	// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
	// =========================================================================

	// возвращает список соответствий цифры кода заказа и ключей данных
	function getLegend() {
		$legend = array();
		if($data = db_get("select type,code from rulesmap left join datatypes on datatypes.id=type_id"))
			foreach($data as $rec)
				$legend[$rec['type']] = $rec['code'];
		return $legend;
	} 

    // возвращает список каналов, подходящих данному номеру телефона
    // формат списка: $channels[$app_id]['priority'=>$p, 'operator'=>$o, 'price'=>$m, 'number'=>$n]
    function getChannels($msisdn) {
    	$channels = array();
    	
    	# Если указан номер, выдаём только каналы данного оператора
    	if($msisdn){
    		if($data = db_get("select app_id, value from apps_conf where key='msisdn'"))
    			foreach($data as $row)
    				if(preg_match('/'.$row['value'].'/', $msisdn))
    					$channels[$row['app_id']] = array();
		}
		
		# Если не нашли ничего (номер не указан или мы таких не знаем), выдаём все каналы
		
		if(!$channels){
			if($data = db_get("select id from apps where active=1 and name like 'channel%'"))
				foreach($data as $row)
					$channels[$row['id']] = array();
		}
    	
    	# Пытаемся добыть данные о каналах
    	
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
    
// разбирает поступившие параметры
function parseRequest($root_level = -1, $request = '') {

	if ($request === '') {
		$request = $_SERVER['REQUEST_URI'];
	};

	// В запросе может быть только один "?" - иначе это нарушение стандартов
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
