<?php
/* ===========================================================================
 *
 * MODULE
 * 	
 * 	reports.webgui.php
 *
 * DESCRIPTION
 *
 * 	This module contains GUI routines and some supplementary functions for
 * 	writing new modules for the report generation susbsystem.
 *
 * AUTHORS
 *
 *	Michael Bochkaryov <misha@netstyle.com.ua>
 *
 * SEE ALSO
 *
 *	1. http://www.php.net/
 *
 * TODO
 *
 *	1. Implement dynamic JS filter for changing activity status of form fields
 *	to be closer to users expectations.
 * 
 * ===========================================================================
 */


include_once 'simple.webgui.php';
include_once 'catalog.webgui.php';
include_once 'excel.php';

class BillingReport extends CMS {

var $report;     // Report identifier for generation
var $reports;    // Available reports list
var $view;       // View type ('h' for HTML and 'x' for CSV)
var $views;      // Available view types
var $since;      // Report period start date
var $till;       // Report period finish date
  
/* ===========================================================================
 * Class constructor
 * Parameters: none
 */
function BillingReport () {

	// This module is implemented over CMS class (as many other)
	$this->CMS('report','report',array(),0);

	// Check if we have report files
	$this->reports = array();
	if (!$report_files = glob(SYS.'/share/reports-old/*.inc.php')) {
		return false;
	};

	// Read reports descriptions
	foreach ($report_files as $filename) {
		if($rc = fopen($filename,'r')){
			$line = fgets($rc);
			// Read descriptions (looks like "//REPORT 123 Some report description" in first line)
			if(preg_match('/\/\/\s*REPORT\s+(\d+)\s+(.+)/',$line,$rm)){
				$this->reports[$rm[1]]['title'] = $rm[2];
				$this->reports[$rm[1]]['file'] = $filename;
			};
			fclose($rc);
		};
	};

	// Manage available actions
	$this->tasks = array(
		'init' => 'return $this->init();', // show report form
		'report' => 'return $this->make_report();' // generate report
		);

	// Manage available views (HTML or CSV)
	$this->views = array(
		'h' => 'HTML',
		'x' => 'CSV (Excel)'
		);

	// Initialize report parameters from HTTP request 

	// Set current date as 'since' parameter
	$this->since = date("Y-m-d");
	$this->till = $this->since;
	if(is_array($_REQUEST['since'])) {
		$this->since = $_REQUEST['since']['y'].'-'.$_REQUEST['since']['m'].'-'.$_REQUEST['since']['d'];
	};
	if(is_array($_REQUEST['till'])) {
		$this->till = $_REQUEST['till']['y'].'-'.$_REQUEST['till']['m'].'-'.$_REQUEST['till']['d'];
	};

	// Set view - default is HTML
	if(!$this->view=trim($_REQUEST['view'])) {
		$this->view = 'h';
	};

	// Select report identifier - default is none
	$this->report=$_REQUEST['mode']+0;

} // function BillingReport
 
 

/* ===========================================================================
 *
 */ 
function init() {

	global $TPL;

	$reports_keys = array_keys($this->reports);
	sort($reports_keys);
	$reports_array = array( '0' => 'Choose report...' );

	foreach($reports_keys as $report_id) {
		$reports_array[$report_id] = $this->reports[$report_id]['title'];
	};

	$addform = '';
	if ($this->report) {
		if (isset($this->reports[$this->report])) {
			$isform = false;
			$formcode = '';
			
			if ($rc = fopen($this->reports[$this->report]['file'],'r')) {
				$headline = fgets($rc);
				$checkform = fgets($rc);
				if (preg_match('/^\/\/FORM/',$checkform)) {
					$isform = true;
					while (($line = fgets($rc)) and !preg_match('/^\/\/ENDFORM.*/',$line)) {
						$formcode .= $line;
					}
				}
				fclose($rc);
			};
			
			if ($isform && $formcode !== '') {
				eval($formcode);
				if (function_exists('report_form')) {
					$addform = report_form();
				}
			}
		}
	}

	// Return report HTML form after template processing
	return template($TPL[$this->prefix.'_init'],array(
		'script' => $_SERVER['SCRIPT_NAME'],
		'since' => date_selector('since',$this->since),
		'till' => date_selector('till',$this->till),
		'mode' => selector('mode',$reports_array,$this->report),
		'view' => selector('view',$this->views,$this->view),
		'disable' => isset($this->reports[$this->report]) ? '' : 'disabled="1"',
		'additional' => $addform
	));

} // function init()
 
 
/* ===========================================================================
 * Function: make_report()
 * Parameters: none
 * Return: none
 *
 * This function implements output of final report form.
 */
function make_report() {

	global $output_open, $TPL, $TS_STR;

	$output_open = false;
	$row_started = 0;

	// Formatting money data
	function money($amount) {
		$string = (string)($amount * 100);
		$string_array = split("\.", $string);
		$int = (int)$string_array[0];
		$return = $int / 100;
		return $return;
	}

	// Starting table
	function start_table() {
		global $reports;
		if ($reports->view != 'x') {
			echo "<table class='content'>\n<tr>";
			$row_started = 1;
		};
	}

	// Finishing table row
	function row() {
		global $reports;
		if ($reports->view == 'x') {
			xls_row();
		} else {
			if ($row_started) { echo "</tr>\n"; };
			echo "<tr>";
			$row_started = 1;
		};
	}

	function end_table(){
		global $reports;
		if ($reports->view != 'x') {
			if($row_started) echo "</tr>\n";
			$row_started = 0;
			echo "</table>\n";
		};
	}

    function img($url){
      global $reports;
      if($reports->view == 'x')
        return $url;
      else
        return '<img src="'.$url.'">';
    }

    function td($text=''){
      global $reports;
      if($reports->view == 'x')
        xls_cell($text);
      else{
        if(!$text) $text = '&nbsp;';
        echo "<td>$text</td>";
      }
    }

    function th($text=''){
      global $reports;
      if($reports->view == 'x')
        xls_cell($text);
      else{
        if(!$text) $text = '&nbsp;';
        echo "<th>$text</th>";
      }
    }

    function rus($digit){ return preg_replace('/(\d+)\.(\d+)/','$1,$2',$digit); }

    function tdn($num=0){
      global $reports;
      $num += 0;
      if($reports->view == 'x')
        xls_cell(rus($num));
      else
        echo "<td>".rus($num)."</td>";
    }

    function thn($num=0){
      global $reports;
      $num += 0;
      if($reports->view == 'x')
        xls_cell(rus($num));
      else
        echo "<th>".rus($num)."</th>";
    }

    function tdc($num=0){
      global $reports;
      $num += 0;
      if($reports->view == 'x')
        xls_cell(rus(number_format($num, 2, ',', '')));
      else
        echo "<td>".rus(number_format($num, 2, ',', ''))."</td>";
    }

    function thc($num=0) {
      global $reports;
      $num += 0;
      if($reports->view == 'x')
        xls_cell(rus(number_format($num, 2, ',', '')));
      else
        echo "<th>".rus(number_format($num, 2, ',', ''))."</th>";
    }


	// ******************************************************
	// Starting report generation

	if($this->view == 'x'){
		xls_header('nibelite-'.$this->report);
	};
    
    $since = $this->since;
    $till = $this->till;
    $channel_id = $this->channel_id;
    
    if($this->view!='x')
      echo $TPL[$this->prefix.'_pagestart'];
    
    // reporting
    if(isset($this->reports[$this->report])){

			// *******************************************
			// Include report file here
      include($this->reports[$this->report]['file']);
			// *******************************************

		} else {
			trigger_error("Cannot instantiate non-existent report: {$this->report}", E_USER_ERROR);
		};
    
    if($this->view != 'x')
      echo $TPL[$this->prefix.'_pageend'];
    return true;
  }
  
}

?>
