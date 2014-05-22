<?php
/* ===========================================================================
 *
 * SVN: $Id: excel.php 159 2008-02-19 11:20:55Z misha $
 *
 * MODULE
 * 	
 * 	excel.php
 *
 * DESCRIPTION
 *
 * 	This module contains API for generating Microsoft Excel compatible CSV
 * 	data from report generator.
 *
 * AUTHORS
 *
 *	Anatoly Matyakh <zmeuka@x-play.com.ua>
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 *	1. Report generator (control/billing.php).
 *
 * TODO
 *
 *	1. Implement XLS and ODS files generation.
 * 
 * ===========================================================================
 */


/* ===========================================================================
 * Function: xls_header($filename='book1')
 * Parameters:
 */
function xls_header($filename='book1') {

	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	header("Pragma: no-cache");
	header("Content-type: application/x-msexcel");
	header("Content-Disposition: attachment; filename=$filename.csv" );
	header("Content-Description: PHP Generated Data" );

} // function xls_header($filename='book1')



/* ===========================================================================
 *
 */
function xls_cell($value) {

	$value = str_replace(array('"',"\t","\n"),array('""',' ',' '),$value);

	if (preg_match('/[\;\"]+/',$value)) {
		$value = '"'.$value.'"';
	};

	echo $value.';';

} // function xls_cell($value)



/* ===========================================================================
 *
 */
function xls_row() {

	echo "\n";

} // function xls_row()

?>
