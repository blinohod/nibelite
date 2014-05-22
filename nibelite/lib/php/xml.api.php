<?php
	/*
	XML Parser Implementation
	$Id: xml.api.php 159 2008-02-19 11:20:55Z misha $
	Designed for Perl and adapted for PHP by Anatoly Matyakh <zmeuka@x-play.com.ua>
	
	Produces document tree as following:
	$XML = 
		<?xml version="1.0" encoding="some-encoding"?>
		<cont id="12" name="My container">
			<item name="An Item" colour="red" />
			<item name="Next Item" flavour="apple">
				There is a text
			</item>
		</cont>
		<descr>
			Here goes some text
			<p>And nested tag</p>
			But we are not going to build a web-browser
		</descr>
		
	$tree = xml_parse_tree($XML);
	
	// See below for further examples
	
	print_r($tree) =>
		Array (
			'cont' => Array (
				0 => Array (
					':attr:' => Array (
						'id' => 12,
						'name'=> 'My container'
					),
					':value:' => '',
					'item' => Array (
						0 => Array (
							':attr:' => Array (
								'name' => 'An Item',
								'colour' => 'red'
							),
							':value:' => ''
						),
						1 => Array (
							':attr:' => Array (
								'name' => 'Next Item',
								'flavour' => 'apple'
							),
							':value:' => 'There is a text'
						)
					)
				)
			)
			'descr' => Array (
				0 => Array (
					':attr:' => Array (),
					':value:' => 'Here goes some text But we are not going to build a web-browser',
					'p' => Array (
						0 => Array (
							':attr:' => Array(),
							':value:' => 'And nested tag'
						)
					)
				)
			)
		);
		
		So some path, at example, attribute "name" from the second "/cont/item" 
		will be resolved as:
		
		$secondItemName = $tree['cont'][0]['item'][1][':attr:']['name'];
		
		To list all item names use 'foreach':
		
		foreach($tree['cont'][0]['item'] as $item)
			print "Item name is: {$item[':attr:']['name']}\n";
			
		And so on. All tags from one level of XML tree will act as numbered array,
		even if there is only one tag. 
			
	*/

function xml_parse_tree($xml){
	global $xIndex;
	$xIndex = 0;
	$tree = array();
	$vals = array();
	$index = array();
	$p = xml_parser_create();
	xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
	xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 1);
	if(xml_parse_into_struct($p, $xml, $vals, $index)){
		xml_parse_tree_tags($tree, $vals);
	}
	xml_parser_free($p);
	return $tree;
}

function xml_parse_tree_tags(&$current, &$vals){
	global $xIndex;
	while(isset($vals[$xIndex])){
		$tag = $vals[$xIndex]['tag'];
		$attr = isset($vals[$xIndex]['attributes']) ? $vals[$xIndex]['attributes'] : array();
		$type = $vals[$xIndex]['type'];
		$value = isset($vals[$xIndex]['value']) ? $vals[$xIndex]['value'] : '';
	
		if($type == 'open'){
			if(!isset($current[$tag])) $current[$tag] = array();
			$n = count($current[$tag]);
			$current[$tag][$n] = array(
				':attr:' => $attr,
				':value:' => $value
			);
			$xIndex++;
			xml_parse_tree_tags($current[$tag][$n], $vals);
		}elseif($type == 'complete'){
			if(!isset($current[$tag])) $current[$tag] = array();
			$n = count($current[$tag]);
			$current[$tag][$n] = array(
				':attr:' => $attr,
				':value:' => $value
			);
			$xIndex++;
		}elseif($type == 'cdata'){
			$current[':value:'] .= "\n{$value}";
			$xIndex++;
		}elseif($type == 'close'){
			$xIndex++;
			return;
		}
	}
}

?>
