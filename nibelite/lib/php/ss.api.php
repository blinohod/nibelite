<?php

//
// Подсистема "Списки совместимости"
//
// $Id: ss.api.php 159 2008-02-19 11:20:55Z misha $
//

require_once 'content.api.php';
require_once 'catalog.api.php';
require_once 'core.inc.php';

class SS {
	
	// инициализация библиотеки
	function init($dbh) {
		SS::dbh($dbh);
	}
	
	// возвращает список совместимости для единицы контента
	function getModelList($content, $format='array', $brandDelim='; ', $colon=true, $brandTag='') {
		$content_id=(is_object($content))?($content->id):(is_numeric($content)?$content:logAndDie("Invalid argument"));
		return SS::_getList($content_id, 'content', $format, $brandDelim, $colon, $brandTag);
	}
	
	// по заданному идентификатору бинарных данных возвращает список совместимости для этих бинарных данных
	function getModelListForBinData($data_id, $format='array', $brandDelim='; ', $colon=true, $brandTag='') {
		return SS::_getList($data_id, 'bindata', $format, $brandDelim, $colon, $brandTag);
	}
	
	// подстановка для WAP: по заданным идентификаторам модели и контента определить, какие данные надо отдать
	// возвращает ключ бинарника
	// если подходящих данных не найдено, возвращает false
	function substitution($model_id, $content) {
		$content_id=(is_a($content, 'Content'))?($content->id):(is_numeric($content)?$content:logAndDie("Invalid argument"));
		if(!is_numeric($model_id)) logAndDie('Invalid argument value ($model_id===' . var_dump($model_id) . ')');
		$max_object_size = SS::_getMaxObjectSize($model_id);
		$selected_bin_key = false;
		// подстановки по типам
		$sql = "select d.filename as fname, d.key, d.type
				from public.data d, public.datatypes t, public.compat c
				where c.model_id=$model_id and t.type=d.key and c.type_id=t.id and d.content_id=$content_id
				order by c.priority desc";
		$result = pg_query(SS::dbh(), $sql) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if(pg_num_rows($result) > 0) {
			while($row = pg_fetch_array($result)) {
				// лимит на размер для mime-типа
				$max_mime_size = SS::_getMaxMimeSize($model_id, $row['type']);
				if( ($max_object_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_object_size) &&
					  ($max_mime_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_mime_size) ) {
					$selected_bin_key = $row['key'];
					break;
				}				
			}
		}
		pg_free_result($result);
		// индивидуальные подстановки
		$sql = "select ci.remove, d.key, d.type, d.filename as fname
				from public.data d, public.compat_ind ci
				where ci.model_id=$model_id and d.content_id=$content_id and ci.storage_id=d.id
				order by remove % 2";
		$result = pg_query(SS::dbh(), $sql) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if(pg_num_rows($result) > 0) {
			while($row = pg_fetch_array($result)) {
				switch($row['remove']) {
				case 0:
				case 2: // remove==2 - непубликуемая подстановка
					$max_mime_size = SS::_getMaxMimeSize($model_id, $row['type']);
					if( ($max_object_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_object_size) &&
						  ($max_mime_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_mime_size) ) {
						$selected_bin_key = $row['key'];
					}
					break;
				case 1:
					$selected_bin_key = false;
					break;
				}
			}
		}
		return $selected_bin_key;
	}
	
	// превращает полученный список идентификаторов моделей в человекочитаемую строку
	function formatModelList($list, $brandDelim='; ', $colon=true, $brandTag='') {
		foreach(SS::convertModelListToHash($list) as $brand => $models) {
			$s = '';
			if($brandTag) $s .= '<'.$brandTag.'>';
			$s .= $brand;
			if($brandTag) $s .= '</'.$brandTag.'>';
			if($colon) $s .= ':';
			$g[] = $s . ' ' . implode(', ', $models);
		}
		return isset($g) ? implode($brandDelim, $g) : "none";
	}

	// по заданному списку единиц контента возвращает наибольший общий список моделей
	function getGreatestCommon($content_set, $format='array', $brandDelim='; ', $colon=true, $brandTag='') {
		foreach($content_set as $content) {
			$list = SS::getModelList($content);
			if(!isset($common)) $common=$list;
			else $common=array_intersect($common, $list);
		}
		if (!isset($common)) $common = array();
		return SS::_formatList(array_values($common), $format, $brandDelim, $colon, $brandTag);
	}

	// для заданного узла каталога возвращает наибольший общий список моделей
	// $recurs - рекурсивно обойти поддерево
	function getFromCatalogGreatestCommon($catalog, $recurs=false, $format='array', $brandDelim='; ', $colon=true, $brandTag='') {
		if (!is_object($catalog) && !is_numeric($catalog)) logAndDie("Invalid argument");
		if (is_numeric($catalog)) $catalog = new Catalog(SS::dbh(), $catalog);
		$list = SS::getGreatestCommon($catalog->getContent());
		if($recurs && $catalog->links) {
			foreach($catalog->links as $link) {
				$list = array_intersect($list, getFromCatalogGreatestCommon($link, true));
			}
		}
		return SS::_formatList($list, $format, $brandDelim, $colon, $brandTag);		
	}

	// по заданному id модели из узла каталога выбрать совместимый контент и вернуть его в виде списка
	// по заданному id модели из списка id'шников контента выбрать совместимый контент и вернуть его в виде списка
	// если модель с id==0, весь контент считать совместимым
	function getContent($list, $model_id=0, $get_obj=false, $sub_content=false) {
		error_log("Catalog: $list");
		if (!is_a($list, 'Catalog') && !is_numeric($list) && !is_array($list)) logAndDie("Invalid argument");
		$model_id+=0;
		if (is_numeric($list) || is_object($list)) {
			if (is_numeric($list)) $list = new Catalog(SS::dbh(), $list);
			if (!$sub_content) {
				$list = $list->getContent();
			} else {
				$list = $list->getSubContent();
			};
		}
		$result = array();
		error_log("SS::getContent.list: " . count($list));
		foreach ($list as $content) {
			if (!$model_id || SS::substitution($model_id, $content)) {
				if(is_numeric($content)) $result[] = $get_obj ? new Content(SS::dbh(), $content) : $content;
				else $result[] = $get_obj ? $content : $content->id;
			}
		}
		error_log("SS::getContent.result: " . count($result));
		return $result;
	}
	
	function getLinks($node, $model_id=0, $get_obj=null) {
		if (!is_a($node, 'Catalog') && !is_numeric($node)) logAndDie('Invalid argument #1');
		$model_id+=0;
		$get_obj = ($get_obj === null) ? is_object($node) : $get_obj;
		if (is_numeric($node)) $node = new Catalog(SS::dbh(), $node);
		$result = array();
		foreach ($node->links as $link_id)
			if (!$model_id || SS::_isCompatibleLink($link_id, $model_id))	// "No model" is always compatible
				$result[] = $get_obj ? new Catalog(SS::dbh(), $link_id) : $link_id;
		return $result;
	}

	function killCache() {
		pg_query(SS::dbh(), "delete from public.profiles where key like 'ss:cache:%'") or logAndDie("DB error: ".pg_last_error(SS::dbh()));
	}

	// private

	function _isCompatibleLink($node_id, $model_id) {
		$sql = "select p.value from public.profiles p where p.key='ss:cache:{$node_id}' and p.model_id={$model_id}";
		$result = pg_query(SS::dbh(), $sql) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if (pg_num_rows($result) > 0) {
			$h = pg_fetch_array($result);
			if (time()-$h['value']<60*60*24) return false;
			$outOfDate = 1;
		}		
		$node = new Catalog(SS::dbh(), $node_id);
		$contentList = $node->getContent();
		foreach ($contentList as $content) {
			if (SS::substitution($model_id, $content)) {
				if(isset($outOfDate))
					pg_query(SS::dbh(), "delete from public.profiles where key='ss:cache:{$node_id}' and model_id={$model_id}") or logAndDie("DB error: ".pg_last_error(SS::dbh()));
				return true;
			}
		}
		foreach ($node->links as $link) {
			if (SS::_isCompatibleLink($link, $model_id)) {
				if(isset($outOfDate))
						pg_query(SS::dbh(), "delete from public.profiles where key='ss:cache:{$node_id}' and model_id={$model_id}") or logAndDie("DB error: ".pg_last_error(SS::dbh()));
				return true;
			}
		}
		pg_query(SS::dbh(), "insert into public.profiles (key, value, model_id) values ('ss:cache:{$node_id}', ".time().", {$model_id})") or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		return false;
	}

	function _getMaxMimeSize($model_id, $mimetype) {
		// лимит на размер для mime-типа
		$sql = "select p.value from public.profiles p where p.key='limit:'||'{$mimetype}' and p.model_id={$model_id}";
		$result = pg_query(SS::dbh(), $sql) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if (pg_num_rows($result) > 0) {
			$h=pg_fetch_array($result);
			$max_mime_size=$h['value'];
		} else {
			$max_mime_size=0;
		}
		pg_free_result($result);
		return $max_mime_size;
	}

	function _getMaxObjectSize($model_id) {	
		$sql = "select p.value from public.profiles p where p.key='max_object_size' and p.model_id=$model_id";
		$result = pg_query(SS::dbh(), $sql) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if (pg_num_rows($result) > 0) {
			$h=pg_fetch_array($result);
			$max_object_size=$h['value'];
		} else {
			$max_object_size=0;
		}
		pg_free_result($result);
		return $max_object_size;
	}

	function _getList($id, $type_of_id, $format, $brandDelim, $colon, $brandTag) {

		switch($type_of_id) {
		case 'content':
				$sql_type =
				   "select c.model_id, d.filename as fname, d.type
						from public.data d, public.datatypes t, public.compat c, public.models m
					where d.content_id=$id and t.type=d.key and c.type_id=t.id and m.visible=1 and m.id=c.model_id";
				$sql_ind =
				   "select ci.model_id, d.filename as fname, ci.remove, d.type
					from public.compat_ind ci, public.data d, public.models m
					where d.content_id=$id and d.id=ci.storage_id and m.visible=1 and m.id=ci.model_id
					order by remove"; // order - сначала добавление совместимости, потом удаление
				break;
		case 'bindata':
				$sql_type =
				   "select c.model_id, d.filename as fname, d.type
					from public.data d, public.datatypes t, public.compat c, public.models m
					where d.id=$id and t.type=d.key and c.type_id=t.id and m.visible=1 and m.id=c.model_id";
				$sql_ind =
					   "select ci.model_id, d.filename as fname, ci.remove, d.type
					from public.compat_ind ci, public.data d, public.models m
					where ci.storage_id=$id and d.id=$id and m.visible=1 and m.id=ci.model_id
					order by remove"; // order - сначала добавление совместимости, потом удаление
				break;
		}

		$complist=array();

		// совместимость по типам
		$result = pg_query(SS::dbh(), $sql_type) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if(pg_num_rows($result) > 0) {
			while($row = pg_fetch_array($result)) {
				if(!in_array($row['model_id'], $complist)) {
					$max_object_size = SS::_getMaxObjectSize($row['model_id']);
					$max_mime_size = SS::_getMaxMimeSize($row['model_id'], $row['type']);
					if( ($max_object_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_object_size) &&
						  ($max_mime_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_mime_size) ) {
							$complist[]=$row['model_id'];
					}
				}
			}
		}
		pg_free_result($result);
																						
		// индивидуальная совместимость
		$result = pg_query(SS::dbh(), $sql_ind) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		if(pg_num_rows($result) > 0) {
			while($row = pg_fetch_array($result)) {
				switch($row['remove']) {
				case 0:				
					if(!in_array($row['model_id'], $complist)) {
							$max_object_size = SS::_getMaxObjectSize($row['model_id']);
						$max_mime_size = SS::_getMaxMimeSize($row['model_id'], $row['type']);
						if( ($max_object_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_object_size) &&
							  ($max_mime_size == 0 || filesize(STORAGE_PATH.$row['fname']) < $max_mime_size) ) {
								$complist[]=$row['model_id'];
						}
					}
					break;
				case 1:
					if(in_array($row['model_id'], $complist))
						unset($complist[array_search($row['model_id'], $complist, true)]);
					break;
				}
			}
		}
		pg_free_result($result);
		
		return SS::_formatList($complist, $format, $brandDelim, $colon, $brandTag);
		
	}

	function _formatList($complist, $format, $brandDelim, $colon, $brandTag) {
		switch($format) {
		case 'html':
			return SS::formatModelList($complist, '<br/>', true, 'strong');
		case 'string':
			return SS::formatModelList($complist, $brandDelim, $colon, $brandTag);
		case 'hash':
			return SS::convertModelListToHash($complist);
		case 'array':
		case 'list':
		default:
			return $complist;
		}	
	}

	function convertModelListToHash($list) {
		if(!$list) return array();
		$sql = "select b.name as brand, m.name as model
				from public.brands b, public.models m
				where m.brand_id=b.id and m.id in (".implode(',', $list).")
				order by b.name, m.name";
		$result = pg_query(SS::dbh(), $sql) or logAndDie("DB error: ".pg_last_error(SS::dbh()));
		$h=array();
		while($row = pg_fetch_array($result)) $h[$row['brand']][] = $row['model'];
		pg_free_result($result);
		return $h;
	}

	function dbh($param=null) {
		static $static;
		return !isset($static)?($static=$param):$static;
	}
	
}


?>
