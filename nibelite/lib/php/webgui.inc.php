<?php
// WebGUI Application Module
// Anatoly Matyakh <protopartorg@gmail.com> $Id$
// Yes, it's just a 'main'-level dispatcher. No classes. No functions. No regret.
// Using hooks:
//      function init_<...> - misc. init functions fired at start
//      function action_<...> - misc. Actions (return value will fill the page content)
//		function action_default - default Action
//		function widget_<...> - misc. page widgets (returning HTML blocks)

$tm_start = array_sum(explode(' ', microtime()));
//error_reporting(E_ALL ^ E_NOTICE);

include_once(SYS . "/etc/nibelite.init.php");

define('APP_NO_OUTPUT',		-1);

$ACTIONS = array();
$WIDGETS = array();
$ACTION = '';
$page_template = 'page';
$_PAGE = array();

function webgui_action($action,$action_handler) {
	global $ACTIONS;
	$ACTIONS[$action] = $action_handler;
}

function webgui_widget($widget,$widget_handler) {
	global $WIDGETS;
	$WIDGETS[$widget] = $widget_handler;
}

function webgui_place($placeholder,$value='') {
	global $_PAGE;
	$_PAGE[$placeholder] = $value;
}

function webgui_add($placeholder,$value='') {
	global $_PAGE;
	if (isset($_PAGE[$placeholder]))
		$_PAGE[$placeholder] .= $value;
	else
		$_PAGE[$placeholder] = $value;
}

function webgui_raw($html) {
	echo $html;
	exit;
}

function webgui_js($js) {
	header('Content-Type: text/javascript');
	echo $js;
	exit;
}

function webgui_init() {
	global $ACTION,$LANGUAGE;
	
	read_templates(TEMPLATES.'design.main.'.$LANGUAGE.'.html');
	
	$funcs = get_defined_functions();
	foreach($funcs['user'] as $funcname){
		if(preg_match('/^action_(.+)$/',$funcname,$m))
			webgui_action($m[1],$funcname);
		elseif(preg_match('/^widget_(.+)$/',$funcname,$m))
			webgui_widget($m[1],$funcname);
		elseif(preg_match('/^init_(.+)$/',$funcname,$m))
			call_user_func($funcname);
	}
			
	$ACTION = strtolower(trim($_REQUEST['do']));
	if($ACTION == '') $ACTION = 'default';
	$ACTION = str_replace('-','_',$ACTION);
}

function webgui_run() {    
	global $ACTIONS,$ACTION,$WIDGETS,$_PAGE,$page_template;
	if(!isset($ACTIONS[$ACTION]))
		if(isset($ACTIONS['default']))
			$ACTION = 'default';
		else
			$ACTION = false;
			
	if($ACTION)
		$_PAGE['page_main'] = call_user_func($ACTIONS[$ACTION]);
	else
		$_PAGE['page_main'] = translate('no_default_action');
		
	if($_PAGE['page_main'] === APP_NO_OUTPUT) exit;

	if(preg_match('/^Redirect: (\S+)/',$_PAGE['page_main'],$match)){
		$addr = $match[1];
		$url = '';
		if(preg_match('/^\w+:\/\//',$addr)){
			$url = $addr;
		}elseif(preg_match('/^\//',$addr)){
			$url = HOST.$addr;
		}else{
			$url = BASE.'/'.$addr;
		}
		header("Location: $url");
		exit;    	
	}

	// Обработка виджетов (асинхронных элементов страницы типа login block и т.д.)
	// Включается только после отработки $ACTION и только для виджетов, чьи "дырки" в странице
	// не перекрылись выводом $ACTION

	foreach($WIDGETS as $widget => $widget_handler)
		if(!isset($_PAGE[$widget]) or $_PAGE[$widget]==='')
			$_PAGE[$widget] = call_user_func($widget_handler);
		
	// $_PAGE['page_time'] = sprintf("%5.3f",array_sum(explode(' ', microtime())) - $tm_start);
	$_PAGE['page_time'] = '';

	echo tpl($page_template,$_PAGE);
}
