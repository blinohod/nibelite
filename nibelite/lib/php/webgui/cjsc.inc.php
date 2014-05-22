<?php
/**
 * cjsc.inc.php
 *
 * @package default
 */


/* 	cjsc.php - Chained JavaScript Calls

	Are you familiar with AJAX? Here's another way to do same things.

	CJSC - 	Simple framework to perform remote procedure calls from within
			JavaScript.
	Of course, the browser must be DOM-enabled (at least MSIE5+ and so on)
	Any XML, XMLHTTPRequest and other stuff like MSXML is not used - we're
	operating on pure JS.
*/

$CJSC_JS_LOADER =
	"bw = new Object()
if(document.getElementById && document.createElement && document.appendChild){
	bw.dom = 1
}

function JSGet(file){
	if(!bw.dom) return true;
	var scriptTag = document.getElementById('loadScript');
	var head = document.getElementsByTagName('head').item(0)
	if(scriptTag) head.removeChild(scriptTag);
	script = document.createElement('script');
	script.src = file;
	script.type = 'text/javascript';
	script.id = 'loadScript';
	head.appendChild(script);
}";


/**
 *
 *
 * @param unknown $tags (optional)
 * @return unknown
 */
function cjsc_loader($tags = true) {
	global $CJSC_JS_LOADER;
	if ($tags)
		return "<script>\n$CJSC_JS_LOADER\n</script>\n";
	else
		return $CJSC_JS_LOADER;
}


/**
 *
 *
 * @Algorithm: http://www1.tip.nl/~t876506/utf8tbl.html
 * @Logic: UTF-8 to Unicode conversion
 * @param unknown $c
 * @return unknown
 * */
function uniord($c) {
	$ud = 0;
	if (ord($c{0})>=0 && ord($c{0})<=127)
		$ud = ord($c{0});
	if (ord($c{0})>=192 && ord($c{0})<=223)
		$ud = (ord($c{0})-192)*64 + (ord($c{1})-128);
	if (ord($c{0})>=224 && ord($c{0})<=239)
		$ud = (ord($c{0})-224)*4096 + (ord($c{1})-128)*64 + (ord($c{2})-128);
	if (ord($c{0})>=240 && ord($c{0})<=247)
		$ud = (ord($c{0})-240)*262144 + (ord($c{1})-128)*4096 + (ord($c{2})-128)*64 + (ord($c{3})-128);
	if (ord($c{0})>=248 && ord($c{0})<=251)
		$ud = (ord($c{0})-248)*16777216 + (ord($c{1})-128)*262144 + (ord($c{2})-128)*4096 + (ord($c{3})-128)*64 + (ord($c{4})-128);
	if (ord($c{0})>=252 && ord($c{0})<=253)
		$ud = (ord($c{0})-252)*1073741824 + (ord($c{1})-128)*16777216 + (ord($c{2})-128)*262144 + (ord($c{3})-128)*4096 + (ord($c{4})-128)*64 + (ord($c{5})-128);
	if (ord($c{0})>=254 && ord($c{0})<=255) //error
		$ud = false;
	return $ud;
}


/**
 *
 *
 * @param unknown $text
 * @return unknown
 */
function percentuencode($text) {
	$len = strlen($text);
	$out = '';
	for ($i=0; $i<$len; $i++) {
		$char = substr($text, $i, 1);
		$uchar = iconv('CP1251', 'UTF-8//TRANSLIT', $char);
		if ($char === $uchar)
			$out .= rawurlencode($char);
		else {
			$out .= '%u';
			$out .= sprintf('%04X', uniord($uchar));
		}
	}
	return $out;
}


/**
 *
 *
 * @param unknown $id
 * @param unknown $html
 * @return unknown
 */
function cjsc_inject_html($id, $html) {
	// $prepared = rawurlencode($html); // PHP:rawurlencode is compatible with JS:unescape
	$prepared = percentuencode($html);
	return
	"if(bw.dom)
	if(document.getElementById('$id'))
		document.getElementById('$id').innerHTML = unescape('$prepared');
";
}


/**
 *
 *
 * @param unknown $js
 * @param unknown $terminate (optional)
 */
function cjsc_respond($js, $terminate=true) {
	header("Content-Type: text/javascript");
	// header("Content-Type: text/javascript");
	print $js;
	if ($terminate) exit;
}


/**
 *
 *
 * @param unknown $uri
 * @return unknown
 */
function cjsc_call($uri) {
	$noca = 'nocache='.time();
	if (preg_match('/\?.+?\=/', $uri))
		$noca = '&'.$noca;
	else
		$noca = '?'.$noca;
	return "{JSGet('".$uri.$noca."');return false;}";
}


?>
