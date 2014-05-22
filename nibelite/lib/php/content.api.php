<?php

//
// Интерфейс Хранилища контента
//
// $Id: content.api.php 159 2008-02-19 11:20:55Z misha $
//
// NOTE:
//   Не нужно добывать всю информацию о контенте всякий раз,
//   когда нужно всего лишь добыть файл по такому-то ключу и $content_id.
//   Для этого добавляются некоторые параметры.
//	 -- zmeuka

require_once "core.inc.php";

// Отладочные штучки. Если включить CDEBUG, кроме кучи сообщений в лог ещё и контент раздаётся кому попало.
define('CDEBUG',false); function argh($str) { if(CDEBUG) error_log($str,3,LOG_FILE); }

// Класс Content
// экземпляр этого класса символизирует единицу контента
class Content {

	var $dbh; // DBMS handler
	var $id; // Content ID from public.content table
	var $class;  // класс контента (cms.classes.class) 
	var $class_id; // идентификатор класса контента (cms.classes.id) 
	var $class_descr; // текстовое описание класса (cms.classes.descr) 
	var $class_descr_ru; // текстовое описание класса (cms.classes.descr_ru) 
	var $active; // true или false 
	var $created; // дата/время создания контента (content.created)
	var $modified; // дата/время модификации контента (content.modified)
	var $path; // путь к каталогу, содержащему бинарные данные (${class_name}/${content_id123}/${content_id456})
	var $meta; // Metadata (hash)
	var $loaded;

	// Конструктор
	// Если идентификатор контента не задан, просто создает пустой объект и возвращает его.
	// Если задан параметр $do_not_load, объект инициализируется с непустым идентификатором, но не читает ничего из базы данных.
	// Иначе вычитывает основные параметры контента и метаданные,
	// загоняя их в переменные объекта.
	// Если такого контента нет - возвращает false.
	function Content ($dbh, $content_id=null, $do_not_load=false) {
		$this->dbh = $dbh;
		$this->meta = array();
		$this->loaded = false;
		if($content_id != null) {
			is_numeric($content_id) or logAndDie("content id isn't numeric ($content_id)");
			$this->id = $content_id;
			$idpad = str_pad($content_id, 9, "0", STR_PAD_LEFT);
			$id123 = substr($idpad, 0, 3);
			$id456 = substr($idpad, 3, 3);
			$id789 = substr($idpad, 6);
			$this->path = $this->class.'/'.$id123.'/'.$id456.'/'.$id789;
			if(!$do_not_load){
				$sql = "select co.id, active, class_id, class, cl.descr, cl.descr_ru, created, modified
						from cms.content co, cms.classes cl
						where co.id = $content_id and cl.id = co.class_id";
				$result = pg_query($this->dbh, $sql) or logAndDie("DB error: ".pg_result_error($result));
				if(pg_num_rows($result)==0) {
					//$this=false;
					return;
				}			
				$row = pg_fetch_array($result);
				pg_free_result($result);
				$this->active = $row['active']=='f'?false:true;
				$this->class = $row['class'];
				$this->class_descr = $row['descr'];
				$this->class_descr_ru = $row['descr_ru'];
				$this->class_id = $row['class_id'];
				$this->created = $row['created'];
				$this->modified = $row['modified'];
				$this->read_meta();
				$this->loaded = true;
			}
		}
		
		
		
	} // Content($dbh, $content_id)

	// getMeta($key, $modif='')
	// возвращает значение параметра метаинформации по его ключу
	// Составной ключ выглядит как <ключ>.<модификатор>
	// Если значения с заданным модификатором в базе нет,
	// возвращается значение для ключа без модификатора.
	function getMeta ($key, $modif = '') {
		if($modif == '' || !$this->meta[$key.'.'.$modif]) return $this->meta[$key];
		return $this->meta[$key.'.'.$modif];
	}
	
	// getMetaList()
	// возвращает список ключей всех имеющихся метаданных
	function getMetaList () {
		return array_keys($this->meta);
	}
	
	// getData($key)
	// возвращает по ключу данные контента
	// Если $get_bin не указывать, то возвращается хеш вида
	//				 id => идентификатор бинаря 
	//				mime_type => MIME тип 
	//				filename => имя файла с поным путем
	//				extension => расширение
	// Если параметр $get_bin установлен в true или 1, то вместо filename возвращается data (содержимое файла).
	// Если данных с таким ключом нет, возвращает false
	function getData ($key, $get_bin=false) {		
		$sql = "select type, id, filename as fname from cms.data
				where content_id = {$this->id} and key = '".pg_escape_string($key)."'";
		$result = pg_query($this->dbh, $sql);
	 	//or logAndDie("DB error: ".pg_result_error($result));
		
		argh("Queried: $sql");
		
		if(pg_num_rows($result)!=1) return false;
		$row = pg_fetch_array($result);
		pg_free_result($result);
		$hash['id']=$row['id'];
		$hash['mime_type']=$row['type'];
		$hash['rel_fname']=$row['fname'];

		$path	 = (strpos($key, 'preview/') === 0) ? PREVIEW_PATH : STORAGE_PATH;
		$web_path = (strpos($key, 'preview/') === 0) ? PREVIEW_WEB_PATH : '';
		
		argh("Filename: ".$path.$row['fname']);
		
		if ($get_bin) {
			if (file_exists($path . $row['fname']))
				$hash['data']=file_get_contents($path . $row['fname']);
		} else {
			$hash['filename'] = $path . $row['fname'];
			$hash['extension'] = substr($hash['filename'], strrpos($hash['filename'], '.') + 1);

			if($web_path) {
				   $hash['webname'] = $web_path . $row['fname'];
			}

		}
		return $hash;
	}

	// getDataList()
	// возвращает список ключей всех имеющихся бинарных данных
	function getDataList () {
		$sql = "select key from cms.data where content_id = {$this->id} order by key";
		$result = pg_query($this->dbh, $sql) or logAndDie("DB error: ".pg_result_error($result));
		$keys=array();
		while($row = pg_fetch_array($result)) {
			$keys[] = $row['key'];
		}
		return $keys;
	}

	// setById($id, [$do_not_load])
	// перечитывает объект из БД по новому идентификатору.
	// По сути, полный аналог конструктора, но не конструктор.
	// В случае невозможности найти, возвращается false.
	function setById ($id, $do_not_load=false) {
		is_numeric($id) or logAndDie("content id isn't numeric ($id)");
		$this->id = $id;
		$idpad = str_pad($content_id, 9, "0", STR_PAD_LEFT);
		$id123 = substr($idpad, 0, 3);
		$id456 = substr($idpad, 3, 3);
		$id789 = substr($idpad, 6);
		$this->path = $this->class.'/'.$id123.'/'.$id456.'/'.$id789;
		$this->meta = array();
		$this->loaded = false;

		if(!$do_not_load){
			$sql = "select co.id, active, class_id, class, cl.descr, created, modified
					from cms.content co, cms.classes cl
					where co.id = $id and cl.id = co.class_id";
			$result = pg_query($this->dbh, $sql) or logAndDie("DB error: ".pg_result_error($result));
			if(pg_num_rows($result)==0) return false;
			$row = pg_fetch_array($result);
			pg_free_result($result);
			$this->active = $row['active']=='f'?false:true;;
			$this->class = $row['class'];
			$this->class_id = $row['class_id'];
			$this->class_descr = $row['descr'];
			$this->created = $row['created'];
			$this->modified = $row['modified'];
			$this->read_meta();
			$this->loaded = true;
		}
		return true;		
	}

	// setByCode($code, $channel_id) 
	// перечитывает контент по коду и идентификатору канала.
	// Похож на конструктор, но это не конструктор.
	// В случае невозможности найти, возвращается false.	
	function setByCode ($code, $channel_id='all') {
		($code !== '') or logAndDie("invalid code (empty string)");
		if ($channel_id == 'all') {
			$sql = "select co.id, active, class_id, class, cl.descr, created, modified
					from cms.content co, cms.classes cl, cms.mapping ma
					where cl.id = co.class_id and ma.code='".pg_escape_string($code)."' and
						ma.content_id=co.id
					limit 1";
		} else {
			is_numeric($channel_id) or logAndDie("content id isn't numeric ($channel_id)");
			$sql = "select co.id, active, class_id, class, cl.descr, created, modified
					from cms.content co, cms.classes cl, cms..mapping ma
					where cl.id = co.class_id and ma.code='".pg_escape_string($code)."' and
						ma.dst_app_id=$channel_id and ma.content_id=co.id";
		}
		$result = pg_query($this->dbh, $sql) or logAndDie("DB error: ".pg_result_error($result));
		if(pg_num_rows($result)==0) return false;
		$row = pg_fetch_array($result);
		pg_free_result($result);									   
		$this->meta = array();
		$this->id = $row['id'];
		$this->active = $row['active']=='f'?false:true;;
		$this->class = $row['class'];
		$this->class_id = $row['class_id'];
		$this->class_descr = $row['descr'];
		$this->created = $row['created'];
		$this->modified = $row['modified'];
		$idpad = str_pad($content_id, 9, "0", STR_PAD_LEFT);
		$id123 = substr($idpad, 0, 3);
		$id456 = substr($idpad, 3, 3);
		$id789 = substr($idpad, 6);
		$this->path = $this->class.'/'.$id123.'/'.$id456.'/'.$id789;
		$this->read_meta();
		$this->loaded = true;
		return true;		
	}
	
	// getCodeList()
	// возвращает список кодов контента, а также для каждого кода - канал (SMS/MMS) и формат отправки
	// Возвращает список хэшей, каждый из которых имеет значения с ключами code, channel
	function getCodeList () {
		$sql = "select ma.code as code, ma.dst_app_id as channel
				from cms.mapping ma
				where ma.content_id={$this->id}";
		$result = pg_query($this->dbh, $sql);
		if(!$result) logAndDie("DB error: ".pg_result_error($result));
		$retval = pg_fetch_all( $result );
		pg_free_result($result);
		return $retval;
	}

	function getCatalogIds () {
		return false;
	}

	// private read_meta()
	// Читает метаинформацию из таблицы public.meta.
	// В здравом уме вызывается только внутри других методов класса.
	function read_meta () {
		$content_id = $this->id;
		$sql = "select m.key as key, m.value as value from cms.meta m where m.content_id = $content_id";
		$result = pg_query($this->dbh, $sql) or logAndDie("DB error: ".pg_result_error($result));
		while ($row = pg_fetch_array($result)) {
			$this->meta[$row['key']] = $row['value'];
		}
		pg_free_result($result);
	} // read_meta()

	function getCatalogPageId() {

		$cid = $this->id;
		if ($data = db_get("select l.cat_id from cms.links l
			join cms.catalog c on (c.id = l.cat_id and c.node_class='page_static')
			where l.content_id = $cid")) {
				return $data[0]['cat_id'];
		} else {
			return 0;
		};
	}

}

?>
