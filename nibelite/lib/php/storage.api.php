<?php
/*
   Nibelung Storage API - Binaries storage in FS and database
   $Id: storage.api.php 159 2008-02-19 11:20:55Z misha $
   Using DB calls via core.inc.php
   Using stored procedures

   TODO:
     class Binary
       data table fields
         id		- binary id
	 content_id	- corresponding content id
	 type		- MIME type (match mime_types)
	 key		- data type (match datatypes)
	 filename	- relative filename
       other 'fields'
         binary data
       Constructor
         Binary()
	   Create a new instance of Binary object.
       Storage search
         seekById($id)
	   load Binary data by id, returns boolean
	 seekByKey($content_id,$key)
	   load Binary data by content id and data type, returns boolean
	 listBinaries($content_id,$keypattern='')
	   list available binary ids and data types for given content id
	   $keypattern is SQL LIKE data type pattern 
	   (where '_' means 'any character' and '%' means 'any substring')
	   returns array( id=>data type )
       Get data
     getFilename
	   just getter, returns a fully-qualified filename
	 getSize
	   returns binary size in bytes
	 getBinary
	   returns the Binary data from Storage
       Set data
         createFromFile($content_id,$key,$type,$filename)
	   create new Binary record
	   returns new record id
	 createFromString($content_id,$key,$type,$buffer)
	   same as above
	 updateKey($key)
	 updateType($type)
	 setString($data)
	   save $data buffer into Storage file
         setFile($filename)
	   copy file contents into Storage file
	 remove
	   oops

*/

require_once 'core.inc.php';

class Binary {
	var $id, $content_id, $type, $key, $filename, $root, $proot;

	//	Just constructor / checker
	
	function Binary($root = STORAGE_PATH, $proot = PREVIEW_PATH){
		global $DBH;
		$this->root = $root;
		$this->proot = $proot;
		if(is_dir($this->root)){
			if(is_dir($this->proot)){
				if($DBH){
					if($id)
						return $this->seekById($id);
					else
						return true;
				}else{
					warn("No database connected"); 
				}
			}else{
				warn("Preview path [".$this->proot."] is not a directory!");
			}
		}else{
			warn("Storage path [".$this->root."] is not a directory!");
		}
		return false;
	}

	//	Search storage
	
	function seekById($id){
		$data = db_get("select content_id,type,key,filename from data where id=".db_escape($id));
		if($data){
			$this->id 		= $id;
			$this->content_id 	= $data[0]['content_id'];
			$this->type 		= $data[0]['type'];
			$this->key 		= $data[0]['key'];
			$this->filename 	= $data[0]['filename'];
			return true;
		}else{
			$this->id = 0;	// to prevent updates
		}
		return false;
	}

	function seekByKey($content_id,$key){
		$data = db_get("select id,type,filename from data where content_id=".db_escape($content_id)
				." and key='".db_escape($key)."'");
		if($data){
			$this->id 			= $data[0]['id'];
			$this->content_id 	= $content_id;
			$this->type 		= $data[0]['type'];
			$this->key 			= $key;
			$this->filename 	= $data[0]['filename'];
			return true;
		}else{
			$this->id = 0;	// to prevent updates
		}
		return false;
	}

	function listBinaries($content_id,$pattern=''){
		$binaries = array();
		$request = "select id,key from data where content_id=".db_escape($content_id);
		if($pattern) $request .= " and key ilike '".db_escape($pattern)."'";
		$request .= " order by key";
		$data = db_get($request);
		if($data)
			foreach($data as $rec)
				$binaries[$rec['id']] = $rec['key'];
		return $binaries;
	}

	// Getters

	function getFilename($rel = ''){
		if($this->filename)
			if($rel!='')
				return $rel . $this->filename;
			else
				return $this->root . $this->filename;
			/*
			// FIXME: It's no good to place previews separately - symlinks forever! // misha@

			if($rel!='')
				return $rel . $this->filename;
			elseif(preg_match('/^preview\//',$this->key))
				return $this->proot . $this->filename;
			else
				return $this->root . $this->filename;
			*/
		else
			return '';
	}
	
	function getSize(){
		if($fname = $this->getFilename())
			return filesize($fname);
		else
			return 0;
	}

	function getBinary(){
		return file_get_contents($this->getFileName());
	}

	// Setters

	function createFromFile($content_id,$key,$type,$sourcename){
		if(is_file($sourcename)){
			$data = db_get("select nextval('data_id_seq'::regclass)");
			if($data){
				if($data[0]['nextval']){
					$this->id = $data[0]['nextval'];
					$this->content_id = $content_id;
					$this->key = $key;
					$this->type = $type;
					if(
						db("insert into data(id,content_id,type,key) values (".
						"{$this->id},{$content_id},'".db_escape($type).
						"','".db_escape($key)."')")
					){
						if($data = db_get("select filename from data where id={$this->id}")){
							$this->filename = $data[0]['filename'];
							$fn = $this->getFilename();
							if($path = dirname($fn)){
								if(!make_path($path)){
									warn("Can't make path [{$path}]");
								}
							}
							if(copy($sourcename,$fn)){

								// We remember about previews // misha@
								$this->addPreview();								
								return $this->id;
							}else{
								warn("Copy from [{$sourcename}] to [{$fn}] failed!");
							}
						}else{
							warn("No data record for id {$this->id}. ".
								"Maybe insert failed?");
						}
					}else{
						warn("New data record insertion failed");
					}
				}else{
					warn("Select nextval('data_id_seq'::regclass) failed");
				}
			}else{
				warn("Select nextval('data_id_seq'::regclass) failed");
			}
		}else{
			warn("No file exists for {$sourcename}");
		}
	}

	function createFromString($content_id,$key,$type,$buffer){
		$data = db_get("select nextval('data_id_seq'::regclass)");
		if($data){
			if($data[0]['nextval']){
				$this->id = $data[0]['nextval'];
				$this->content_id = $content_id;
				$this->key = $key;
				$this->type = $type;
				if(
					db("insert into data(id,content_id,type,key) values (".
					"{$this->id},{$content_id},'".db_escape($type).
					"','".db_escape($key)."')")
				){
					if($data = db_get("select filename from data where id={$this->id}")){
						$this->filename = $data[0]['filename'];
						$fn = $this->getFilename();
						if($path = dirname($fn)){
							if(!make_path($path)){
								warn("Can't make path [{$path}]");
							}
						}
						if(file_put_contents($fn,$buffer)){
							$this->addPreview();
							return $this->id;
						}else{
							warn("Write to [{$fn}] failed!");
							return $this->id;
						}
					}else{
						warn("No data record for id {$this->id}. ".
							"Maybe insert failed?");
					}
				}else{
					warn("New data record insertion failed");
				}
			}else{
				warn("Select nextval('data_id_seq'::regclass) failed");
			}
		}else{
			warn("Select nextval('data_id_seq'::regclass) failed");
		}
	}

	function updateKey($key){
		if($this->id){
			if(db("update data set key='".db_quote($key)."' where id={$this->id}")){
				$this->key = $key;
				return true;
			}else{
				warn("Update data record (id: {$this->id}) failed");
			}
		}else{
			warn("Attempt to update uninitialized Binary");
		}
		return false;
	}

	function updateType($type){
		if($this->id){
			if(db("update data set type='".db_quote($type)."' where id={$this->id}")){
				$this->type = $type;
				return true;
			}else{
				warn("Update data record (id: {$this->id}) failed");
			}
		}else{
			warn("Attempt to update uninitialized Binary");
		}
		return false;
	}

	function setString($buffer){
		if($this->id){
			if($fn = $this->getFilename()){
				if($path = dirname($fn)){
					if(!make_path($path)){
						warn("Can't make path [{$path}]");
					}
				}
				if(file_put_contents($fn,$buffer)){
					$this->addPreview();
					return true;
				}else{
					warn("0 bytes written to [{$fn}]");
				}
			}else{
				warn("No filename for data record {$this->id}");
			}
		}else{
			warn("Attempt to update uninitialized Binary");
		}
		return false;
	}

	
	function setFile($sourcename){
		if($this->id){
			if($fn = $this->getFilename()){
				if($path = dirname($fn)){
					if(!make_path($path)){
						warn("Can't make path [{$path}]");
					}
				}
				if(is_file($sourcename)){
					if(copy($sourcename,$fn)){
						$this->addPreview();
						return true;
					}else{
						warn("Can't copy [{$sourcename}] to [{$fn}]");
					}
				}else{
					warn("No file [{$sourcename}]");
				}
			}else{
				warn("No filename for data record {$this->id}");
			}
		}else{
			warn("Attempt to update uninitialized Binary");
		}
		return false;
	}

	function remove(){
		if($this->id){
			if(db("delete from data where id={$this->id}")){
				$this->removePreview();
				unlink($this->getFilename());  // no warnings anyway - useless
				$this->id = 0;
				return true;
			}else{
				warn("Deletion of data record {$this->id} failed");
			}
		}else{
			warn("Attempt to delete uninitialized Binary");
		}
		return false;
	}

	// Function addPreview adds symlink to preview catalog if necessary
	function addPreview() {
		if (!$this->id) {
			warn("Cant add symlink, provide bin Id plz");
			return false;
		}
		if (preg_match('/^preview\//',$this->key)) {
			$fname = $this->getFilename();
			$pre_fname = $this->getFilename(PREVIEW_PATH);
			if ($pre_path = dirname($pre_fname)) {
				if (make_path($pre_path)) {
					symlink($fname,$pre_fname); // FIXME: Hope it works always // misha@
				} else {
					warn("Cant make symlink dir [{$pre_path}]");
				}
			}
		} // end preview symlinking
	} // end: function addPreview

	// Function removePreview() removes symlinks from preview directory
	function removePreview() { 
		if (!$this->id) {
			warn("Cant remove unexisting symlink");
			return false;
		};

		if (preg_match('/^preview\//',$this->key)) {
			unlink($this->getFilename(PREVIEW_PATH)); // It's symlink
		}			

	}

}

?>
