<?php 
// $Id: getdata.php 32 2007-10-24 09:23:33Z misha $

include_once 'init.inc.php';
include_once 'storage.api.php';

face_control('content');

$data_id = $_REQUEST['data_id']+0;
if(!$data_id) send_user_far_away();
$bin = new Binary();
if(!$bin) send_user_far_away('Error initialising Binary');
if(!$bin->seekById($data_id)) send_user_far_away('Invalid Barcode');

$filename = $bin->getFilename();
if(!$filename) send_user_far_away('Error getting Filename');
$filename = preg_replace('/^(.*\/)?(.+?)$/','$2',$filename);
$content_type = $bin->type;
$size = $bin->getSize();

header("Content-Type: $content_type");
header("Content-Length: $size");
header("Content-Disposition: attachment; filename=\"$filename\"");
echo($bin->getBinary());
exit;

function send_user_far_away($reason = ''){
	header("HTTP/1.0 400 Bad Request");
	echo "\n\n";
	if($reason) echo "$reason\n";
	exit;
}

?>
