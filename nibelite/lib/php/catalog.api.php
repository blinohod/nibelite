<?php
/* ===========================================================================
 *
 * SVN: $Id: catalog.api.php 159 2008-02-19 11:20:55Z misha $
 *
 * MODULE
 * 	
 * 	catalog.api.php
 *
 * DESCRIPTION
 *
 * 	This module implements API to Nibelite catalog.
 *
 * AUTHORS
 *
 *	Anatoly Matyakh <zmeuka@x-play.com.ua>
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 * TODO
 *
 * ===========================================================================
 */


require_once("core.inc.php");


/* ===========================================================================
 * Class: Catalog
 *
 * Class implements common Nibelite catalog API.
 * Object of this class represent one catalog node.
 */
class Catalog {

var $id;         // Unique node identifier in DBMS.
var $parent_id;  // Parent node identifier.
var $title;      // Catalog node title.
var $links;      // Children nodes identifiers list.
var $sub_links;  // Recursice subtree nodes identifiers list.
var $keywords;   // Keywords list (for search functionality).
var $dbh;        // PostgreSQL DBMS handler.
var $node_class; // Node class (content, page_static, etc).


/* ===========================================================================
 * Class constructor
 * Parameters: database handler, catalog node identifier
 *
 * Constructor creates new object and read all object data from DB
 * including children nodes and all subtree nodes identifiers lists.
 */
function Catalog ($dbh, $cat_id) {

	$this->dbh = $dbh;

	// Fetch node information from DB.
	if ($row = db_get("select * from cms.catalog where id=$cat_id")) {
		$this->id = $row[0]['id'];
		$this->parent_id = $row[0]['parent_id'];
		$this->title = $row[0]['title'];
		$this->keywords = $row[0]['keywords'];
		$this->node_class = $row[0]['node_class'];
	} else {
		$this = false;
		return;
	};

	// Create children nodes list.
	$this->links = array();

	if ($data = db_get("select id from cms.catalog where parent_id=" . $this->id . " order by sort desc")) {
		foreach ($data as $row) {
			$this->links[] = $row['id'];
		};
	};

	// Fetch sublinks from DB
	$this->sub_links = array();
	if ($data = db_get("select id from cms.catalog where id in (select * from get_subcatalogs('" . $this->id . "'))")) {
		foreach ($data as $row) {
			$this->sub_links[] = $row['id'];
		};
	};

} // function Catalog ($dbh, $cat_id)



/* ===========================================================================
 * Method: getSubContent($get_obj=false)
 * Parameters: 
 * Returns: list of Content class objects linked to this node and all subtree.
 */
function getSubContent($get_obj=false) {

	return $this->getContent($get_obj, true);

} //function getSubContent($get_obj=false)



/* ===========================================================================
 *
 */
// Служебная функция, генерирует список идентификаторов единиц контента
function _generateSubCatalogPgString() {

	$result = $this->id;
	if (count($this->sub_links) <= 0) {
		return $this->id;
	} else {

		for ($i = 0; $i < count($this->sub_links); $i++) {
			if ($i == 0) {
				$result .= $this->sub_links[$i];
			} else {
				$result .= ", " . $this->sub_links[$i];
			};
		};
	};

	return $result;

} //function _generateSubCatalogPgString()



/* ===========================================================================
 *
 */
function getTopSaleContent($get_obj=false, $sub_content=false) {

} // function getTopSaleContent($get_obj=false, $sub_content=false)



/* ===========================================================================
 * Method: getContent ($get_obj, $sub_content)
 *
 * возвращает список записей контента, привязанных к данному узлу каталога.
 */
function getContent($get_obj=false, $sub_content=false) {


/*		$sql = "select l.content_id
			from public.links l, public.content c
			where c.id=l.content_id and c.active=TRUE and l.cat_id=" . $this->id;*/
	if (!$sub_content) {
		$sql = 
"SELECT
	l.content_id
FROM
	cms.links l,
	cms.content c
JOIN 
	cms.meta m
ON
	c.id=m.content_id
WHERE
	c.id=l.content_id AND
	c.active=true AND
	m.key='title.en' AND
	l.cat_id=" . $this->id .
" ORDER BY
	m.value";

	} else {

		$sql = 
"SELECT
	l.content_id
FROM
	cms.links l,
	cms.content c
JOIN 
	cms.meta m
ON
	c.id=m.content_id
WHERE
	c.id=l.content_id AND
	c.active=true AND
	m.key='title.en' AND
	l.cat_id in (" . $this->_generateSubCatalogPgString() . ") " .
" ORDER BY
	m.value";
	};

	$result = pg_query($this->dbh, $sql)
		or logAndDie("DB error: ".pg_result_error($result));
	$content_list = array();	    
	$rownum = 0;

	while ($row = pg_fetch_array($result)) {
		$rownum++;
		$content_list[] = $get_obj ? (new Content($this->dbh, $row['content_id'])) : $row['content_id'];
	};

	pg_free_result($result);
	return $content_list;

} // function getContent($get_obj=false, $sub_content=false)



/* ===========================================================================
 * Method: getLinkedContent($get_obj)
 *
 * возвращает список записей контента, привязанных к данному узлу каталога.
 */
function getLinkedPage() {

$sql = "SELECT
	l.content_id
FROM
	cms.links l,
	cms.content c
	join cms.classes cl on (cl.class='page-descriptor' and cl.id = c.class_id)
WHERE
	c.id=l.content_id AND
	c.active=true AND
	l.cat_id=" . $this->id ;

	$result = pg_query($this->dbh, $sql)
		or logAndDie("DB error: ".pg_result_error($result));
	$content_list = array();	    
	$rownum = 0;

	while ($row = pg_fetch_array($result)) {
		$rownum++;
		$content_list[] = $get_obj ? (new Content($this->dbh, $row['content_id'])) : $row['content_id'];
	};

	pg_free_result($result);
	return $content_list;

} // function getLinkedPage($get_obj=false, $sub_content=false)



/* ===========================================================================
 * Method: getLinkedContent($get_obj)
 *
 * возвращает список записей контента, привязанных к данному узлу каталога.
 */
function getLinkedContent($class_name) {

$sql = "SELECT
	l.content_id
FROM
	cms.links l,
	cms.content c
	join cms.classes cl on (cl.class='$class_name' and cl.id = c.class_id)
WHERE
	c.id=l.content_id AND
	c.active=true AND
	l.cat_id=" . $this->id ;

	$result = pg_query($this->dbh, $sql)
		or logAndDie("DB error: ".pg_result_error($result));
	$content_list = array();	    
	$rownum = 0;

	while ($row = pg_fetch_array($result)) {
		$rownum++;
		$content_list[] = new Content($this->dbh, $row['content_id']);
	};

	pg_free_result($result);
	return $content_list;

} // function getLinkedPage($get_obj=false, $sub_content=false)

} // class Catalog

?>
