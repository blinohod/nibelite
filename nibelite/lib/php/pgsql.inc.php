<?php
/* ===========================================================================
 *
 * MODULE
 * 	
 * 	pgsql.inc.php
 *
 * DESCRIPTION
 *
 * 	This module contains low level DBMS functions implemented in procedure style.
 * 	
 * 	Module requires $CONFIG global variable to have DBMS connection settings and
 * 	non false $DEBUG_DB to retrieve debug information.
 *
 * 	Connection handler stored in $DBH global variable.
 *
 * AUTHORS
 *
 *	Anatoly Matyakh <zmeuka@x-play.com.ua>
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 *	1. http://www.php.net/
 *	2. http://www.postgresql.org/
 *
 * TODO
 *
 *	1. Implement OO style version.
 *	2. Remove OID dependencies from module.
 *	3. Implement multi DB connectivity.
 * 
 * ===========================================================================
 */



/* ===========================================================================
 * Function: db_connect()
 * Parameters: none
 * Return: none
 *
 * Function reads DBMS connection parameters from $CONFIG, connects to DBMS
 * and put handler to $DBH global variable.
 */
function db_connect() {

	global $CONFIG, $DBH;
 
	$connect_string = "";
	if ($CONFIG['db_name']) { $connect_string .= "dbname=".$CONFIG['db_name']; }
	if ($CONFIG['db_user']) { $connect_string .= " user=".$CONFIG['db_user']; }
	if ($CONFIG['db_pass']) { $connect_string .= " password=".$CONFIG['db_pass']; }
	if ($CONFIG['db_host']) { $connect_string .= " host=".$CONFIG['db_host']; }
	if ($CONFIG['db_port']) { $connect_string .= " port=".$CONFIG['db_port']; }

	$DBH = pg_pconnect($connect_string);
	if (!$DBH) { exit; } // FIXME: Do something better than suicide !!!!!!!!!!!!
	db('set datestyle to german');
	db('set client_encoding to \'UTF8\''); // FIXME: Sometimes we'll be UTF-8 here %) -- misha@
	
} // function db_connect()



/* ===========================================================================
 * Function: db_escape($string)
 * Parameters: unescaped string
 * Return: escaped string
 */
function db_escape($str) {

  return pg_escape_string($str);

} // function db_escape($str)



/* ===========================================================================
 * Function: db_get( $sql_select_query )
 * Parameters: SQL select query
 * Return: result of select as rows array
 *
 * Example:
 * $tmp = db_get("select now() as nowdate");
 * $db_date_string = $tmp[0]['nowdate'];
 */
function db_get($select_query) {

  global $DBH, $DEBUG_DB;

  if($DEBUG_DB) {
    echo "<b>*** DB Get [</b> $select_query <b>]</b><br>";
    flush();
  }
  $dbr = pg_query($DBH, $select_query);
  $all = false;

  if ($dbr) {
    $all = pg_fetch_all ($dbr);
	} else {
		echo "DB_GET FAILED: $select_query <br>". pg_last_error($DBH)."<br>";
	};

  return $all;

} // function db_get($select_query)



/* ===========================================================================
 * Function: db_get_arr($select_query)
 * Parameters: SQL select query
 * Return: select result as key=>value hash
 * 
 * This function may be used only for cases select query returns rows of two
 * fields, where first field treated as hash key and second as value.
 *
 * For example:
 * db_get_arr("select id, class from cms.classes") may return something like this
 * Array(
 * '2' => 'ringtone-mono',
 * '8' => 'ringtone-poly',
 * );
 */
function db_get_arr($select_query) {

	global $DBH, $DEBUG_DB;
	
	if ($DEBUG_DB) {
    echo "<b>*** DB Get [</b> $select_query <b>]</b><br>";
    flush();
	};

	$dbr = pg_query($DBH, $select_query);
	$all = false;

	if ($dbr) {

		for($i=0;$i<pg_num_rows($dbr);$i++) {
			$arr =  pg_fetch_row($dbr);
			$all[$arr[0]] = $arr[1];
		};

	} else {
		echo "DB_GET_ARR FAILED: $select_query <br>". pg_last_error($DBH)."<br>";
	};

  return $all;

} // function db_get_arr($select_query)



/* ===========================================================================
 * Function: db_put($insert_query)
 * Parameters: SQL insert query
 * Return: last inserted OID.
 */
function db_put($insert_query) {

  global $DBH, $DEBUG_DB, $SKIP_DB;

	if ($DEBUG_DB) {
		echo "<b>*** DB Put [</b> $insert_query <b>]</b><br>";
	};

	if ($SKIP_DB) {
		return 1;
	};

	$dbr = pg_query($DBH, $insert_query);
	if (!$dbr) {
		echo "DB_PUT FAILED: $insert_query <br>\n". pg_last_error($DBH)."<br>";
	};

  return pg_last_oid($dbr);

} // function db_put($insert_query)



/* ===========================================================================
 * Fucntion: db($query)
 * Parameters: any SQL query
 * Return: result
 *
 * This function may be used for queries not returning data or OIDs.
 *
 * Example:
 * db("update content set active = 'false'"); // content switched off
 */
function db($query) {

	global $DBH, $DEBUG_DB, $SKIP_DB;

	if ($DEBUG_DB) {
		echo "<b>*** DB [</b> $query <b>]</b><br>";
	};

	if ($SKIP_DB) {
		return 1;
	};

	$dbr = pg_query($DBH, $query);
	if (!$dbr) {
		echo "DB FAILED: $query <br>". pg_last_error($DBH)."<br>";
	};

  return $dbr;

} // function db($query)

?>
