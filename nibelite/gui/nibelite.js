/*
 * Nibelite 5.0 core GUI functionality
 */

$(function() {

	// Determine GUI module path
	var nlPath = window.location.pathname;

	if (nlPath == '/nibelite/gui/') {
		nlPath = 'portal/default.html';
	} else {
		nlPath = nlPath.replace('/nibelite/gui/s/','');
	};

	$("#menu").load('/nibelite/gui/fcgi/portal/menu.html');

	// Load GUI module to work zone including parameters
	$("#workzone").load('/nibelite/gui/fcgi/' + nlPath + window.location.search);

});

/*
 * GUI 
 */
function nlLoad(url, id) {
	id = '#'+id;
	$(id).load(url);
	$(id).show();
}

