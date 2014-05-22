<?php   //      PROJECT:        Nibelite IV SMSNews
        //      MODULE:         Subscriber Categories Admin module
        //		Anatoly Matyakh <protopartorg@gmail.com>
        //      $Id$

function action_smsnews_catlist() {
	$items = '';
	
	$data = db_get("select id,category,descr from smsnews.categories order by category");
	if ($data) {
		foreach ($data as $rec) {
			if ($rec['category'] != 'check')
				$items .= tpl('smsnews_cat_list_item',array(
					'id' => $rec['id'],
					'category' => htmlspecialchars($rec['category']),
					'descr' => htmlspecialchars($rec['descr'])
				));
		}
	}
	
	return tpl('smsnews_cat_list',array(
		'items' => $items
	));
}

function action_smsnews_catadd() {
	$category = $_REQUEST['name'].'';
	$descr = $_REQUEST['descr'].'';
	$id = 0;
	
	$raw = 'ERROR';
	
	if ($category) { // $descr is optional
		$data = db_get("select id from smsnews.categories where category='".db_escape($category)."'");
		if ($data) {
			$raw = 'DUPLICATE';
		} else {
			$data = db_get("insert into smsnews.categories (category,descr) values ('".db_escape($category)."','".db_escape($descr)."') returning id");
			if ($data) {
				$id = $data[0]['id'];
				smsnews_log('category added',"id=$id, category=$category, descr=$descr");

				$raw = tpl('smsnews_cat_list_item',array(
					'id' => $id,
					'category' => htmlspecialchars($category),
					'descr' => htmlspecialchars($descr)
				));
			}
		}
	} else {
		$raw = 'DATA ERROR';
	}
	
	webgui_raw($raw);
}

function action_smsnews_catupdate() {
	$category = $_REQUEST['name'].'';
	$descr = $_REQUEST['descr'].'';
	$id = $_REQUEST['id']+0;
	
	$raw = 'ERROR';
	
	if ($id && $category) { // $descr is optional
		$data = db_get("select id from smsnews.categories where id!=$id and category='".db_escape($category)."'");
		if ($data) {
			$raw = 'DUPLICATE';
		} else {
			$data = db("update smsnews.categories set category='".db_escape($category)."', descr='".db_escape($descr)."' where id=$id");
			if ($data) {
				smsnews_log('category updated',"id=$id, category=$category, descr=$descr");
				$raw = tpl('smsnews_cat_list_item',array(
					'id' => $id,
					'category' => htmlspecialchars($category),
					'descr' => htmlspecialchars($descr)
				));
			}
		}
	} else {
		$raw = 'DATA ERROR';
	}

	webgui_raw($raw);
}

function action_smsnews_catdelete() {
	$id = $_REQUEST['id']+0;
	$raw = 'ERROR';
	
	if ($id) {
		$data = db_get("select id from smsnews.subscribers where category_id=$id limit 1");
		if (!$data) {
			if (db("delete from smsnews.categories where id=$id")) {
				$raw = 'DELETED';
				smsnews_log('category deleted',"id=$id");
			}
		} else {
			$raw = 'NOT EMPTY';
		}
	}
	
	webgui_raw($raw);
}
