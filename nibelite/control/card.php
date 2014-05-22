<?php 
// $Id: card.php 26 2007-09-19 16:58:45Z misha $
include_once 'init.inc.php'; 

include_once 'webgui/simple.webgui.php';
include_once 'webgui/cards.webgui.php'; 

$status = '';
$page_main = '&nbsp;';

face_control('support');

$cards = new CMSCards();

if(!($page_main = $cards->handle()))
  $page_main = '<h1>404 Missed, dude</h1>';

echo template($TPL['popup'],array(
  'page_title'  => translate('cards_title'),
  'page_main'   => $page_main
));


?>

