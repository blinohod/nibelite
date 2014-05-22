<?php

// Compatibility Tables API (very light version)
// zmeuka@x-play.com.ua : 2006-08-18
//
// $Id: compat.api.php 159 2008-02-19 11:20:55Z misha $
//

require_once "core.inc.php";

class Compat {

	var $models;
	var $models_byid;
	var $error;
	
	function Compat(){
		$this->models = array();
		$this->models_byid = array();
		if($tmp = db_get(
			"select b.name as brand, m.name as model, m.id as model_id".
			" from models m".
				" left join brands b on b.id=m.brand_id".
			" order by brand, model"
		))
			foreach($tmp as $rec){
				$brand = strtolower($rec['brand']);
				$model = strtolower($rec['model']);
				if(isset($this->models[$brand]))
					$this->models[$brand][$model] = $rec['model_id'];
				else
					$this->models[$brand] = array( $model => $rec['model_id'] );
				$this->models_byid[$rec['model_id']] = array( 'b' => $rec['brand'], 'm' => $rec['model'] );
			}
	}

	
	// Внутренняя штучка: получить data.id по content.id + data.key
	
	function getDataByKey($content_id,$key){
		if($tmp = db_get("select id from cms.data where content_id=$content_id and key='".db_escape($key)."'"))
			return $tmp[0]['id'];
		else
			$this->error = "No data found with content_id [$content_id] and key [$key]";
		return 0;
	}
	
	// Внутренняя штучка: получить datatype.id по datatype
	
	function getDatatypeId($datatype){
		if($tmp = db_get("select id from cms.datatype where type='".db_escape($datatype)."'"))
			return $tmp[0]['id'];
		else
			$this->error = "No datatype found like [$datatype]";
		return 0;
	}
	
	// Создать строку совместимости по данному массиву совместимости (описание массива см.ниже)
	// Опционально создаёт форматированную в HTML строку с выделениями брэндов и вычёркиванием
	
	function createString($compat,$html=false){
		if(is_array($compat)){
			$compat_str = '';
			foreach($compat as $brand=>$models){
				$compat_str .= ($html) ? "<b>$brand</b>: " : "$brand: ";
				$mod = array();
				foreach($models as $model=>$add)
					$mod[] = ($add) ? $model : (($html) ? "-<s>$model</s>" : "-$model");
				$compat_str .= implode(',', $mod) . '; ';
			}
			return $compat_str;
		}else
			return '';
	}
	
	
	// преобразовать строку совместимости в массив
	
	function parseString($compat_str){
		// Strip corner whitespace, HTML and caps
		$compat_str = preg_replace('/\<.+?\>/','',strtolower(trim($compat_str)));
		$compat = array();
		if(!($compat_str==='')){
			foreach(explode(';',$compat_str) as $brandline){
				if(trim($brandline)){
					list($brand,$models) = explode(':',$brandline);
					$brand = trim($brand);
					foreach(explode(',',$models) as $model){
						$model = trim($model);
						if($model){
							$comp = 1;
							if(strpos($model,'-')===0){
								$model = substr($model,1);
								$comp = 0;
							}
							if(isset($compat[$brand]))
								$compat[$brand][$model] = $comp;
							else
								$compat[$brand] = array($model => $comp);
						}
					}
				}
			}
		}
		return $compat;
	}

	
	// Получить массив (хэш [brand_name][model_name] => $r) моделей телефонов,
	// записанных как индивидуально совместимые с конкретными данными.
	// $r - показатель "совместим (1) / не совместим (0)" 
	
	function getIndCompat($binary_id){
		$compat = array();
		if($tmp = db_get(
			"select model_id, remove from cms.compat_ind where storage_id=$binary_id"
		)){
			foreach($tmp as $rec){
				$rem = ($rec['remove']) ? 0 : 1;
				$mid = $rec['model_id'];
				if(isset($this->models_byid[$mid]))
					if(isset($compat[$this->models_byid[$mid]['b']]))
						$compat[ $this->models_byid[$mid]['b'] ][ $this->models_byid[$mid]['m'] ] = $rem;
					else
						$compat[ $this->models_byid[$mid]['b'] ] = array( 
							$this->models_byid[$mid]['m'] => $rem 
						);
			}
			foreach($compat as $brand=>$models) ksort($compat[$brand]);
			ksort($compat);
		}
		
		return $compat;
	}
	
	// То же самое по $content_id + $key
	
	function getIndCompatByKey($content_id,$key){
		if($binary_id = $this->getDataByKey($content_id,$key))
			return $this->getIndCompat($binary_id);
		else
			return array();
	}
	
	
	// Записать индивидуальную совместимость из массива
	
	function setIndCompat($binary_id,$compat){
		$this->error = '';
		if($binary_id){
			db("delete from cms.compat_ind where storage_id=$binary_id");
			if(is_array($compat))
				foreach($compat as $brand=>$mod){
					foreach($mod as $model=>$comp)
						if(isset($this->models[$brand][$model])){
							$rem = ($comp) ? 0 : 1;
							db("insert into cms.compat_ind(storage_id, model_id, remove)".
								" values($binary_id,{$this->models[$brand][$model]},$rem)");
						}else
							$this->error .= sprintf(translate('content_compat_nomodel'), "$brand $model").' : ';
				}
		}else{
			$this->error = "Bad Binary ID [$binary_id]";
			return false;
		}
		return true;
	}
						
						
	// То же, но по ключу
						
	function setIndCompatByKey($content_id,$key,$compat){
		if($binary_id = $this->getDataByKey($content_id,$key))
			return $this->setIndCompat($binary_id,$compat);
		else
			return false;
	}
	
	
	
	// Получить массив (хэш [brand_name][model_name] => $r) моделей телефонов,
	// записанных как совместимые с типом данных.
	// $r - показатель "совместим (1) / не совместим (0)" 
	
	function getTypeCompat($datatype){
		if(preg_match('/^\d+$/',$datatype))	// Если нам дали datatype_id цифровое
			$datatype_id = $datatype;
		else								// Если строка - ищем такой тип
			$datatype_id = $this->getDatatypeId($datatype);
		if(!$datatype_id) return array();
	
		$compat = array();
		if($tmp = db_get(
			"select model_id from cms.compat where type_id=$datatype_id"
		)){
			foreach($tmp as $rec){
				$rem = 1;
				$mid = $rec['model_id'];
				if(isset($this->models_byid[$mid]))
					if(isset($compat[$this->models_byid[$mid]['b']]))
						$compat[ $this->models_byid[$mid]['b'] ][ $this->models_byid[$mid]['m'] ] = $rem;
					else
						$compat[ $this->models_byid[$mid]['b'] ] = array( 
							$this->models_byid[$mid]['m'] => $rem 
						);
			}
			foreach($compat as $brand=>$models) ksort($compat[$brand]);
			ksort($compat);
		}
		
		return $compat;
	}
	
	
	// Записать совместимость для типа данных из массива
	
	function setTypeCompat($datatype,$compat){
		if(preg_match('/^\d+$/',$datatype))	// Если нам дали datatype_id цифровое
			$datatype_id = $datatype;
		else								// Если строка - ищем такой тип
			$datatype_id = $this->getDatatypeId($datatype);
		if(!$datatype_id) return false;
	
		$this->error = '';
		db("delete from cms.compat where type_id=$datatype_id");
		if(is_array($compat))
			foreach($compat as $brand=>$mod)
				foreach($mod as $model=>$comp)
					if(isset($this->models[$brand][$model])){
						// priority не поддерживается
						db("insert into cms.compat(type_id, model_id)".
							" values($datatype_id,{$this->models[$brand][$model]})");
					}else
						$this->error .= sprintf(translate('content_compat_nomodel'), "$brand $model").' : ';
		return true;
	}
}

?>
