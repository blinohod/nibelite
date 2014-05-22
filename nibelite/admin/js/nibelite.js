/**
 * Nibelite V main Javascript
 * 
 * Anatoly 'zmeuka' Matyakh <protopartorg@gmail.com> 2011-10-18
 **/
 
var nlService = 'core';
var nlScreen = 'blank';
var nlNewService = false;
var nlNewScreen = false;
var nlMenuService = '';
var nlScreens = { core: { blank: true } };
var PREFIX = '/nibelite/admin/fcgi';
var nlUser = '';
var nlUserId = 0;
var nlSameClicks = 0;
var nlAuthorized = false;
var nlMenuHidden = false;

var nlInitialHash = [];
var nlInitialService = false;
var nlInitialScreen = false;

var nlFailedStrings = new Object ();

var nlBodyMargins = 8;
var nlMainMargins = 1;
var nlSplitMargins = 0;
var nlSplitOuterWidth = 8;
var nlMenuOuterWidth = 208;
var nlMenuMargins = 4;
var nlBMargins = 0;
var nlHeadHeight = 36;

var nlTr = {
	lang: 'en',
	editor: 0,
	strings: {
		core: {
			request_sent: 'Waiting for response...',
			you_are_logged: 'You are logged in as %username',
			login_failed: 'Login failed',
			logout_failed: 'The server failed to log out',
			logout_ok: 'You are logged out',
			error: 'Error',
			log_in: 'Please log in',
			no_main_menu: 'No main menu items found',
			service_load_ok: 'Service [%service] loaded',
			service_load_error: 'Error loading service [%service]: %error',
			i18n_loaded: 'Language data loaded',
			i18n_load_error: 'Problem loading language data',
			i18n_no_data: 'Empty language data arrived. Perhaps you have configured dead language.',
			
			version: "v. 5.0.0 'Vollmond'",
			core_descr: 'MVAS management and provisioning platform',
			init: 'initializing',
			username: 'Username',
			password: 'Password',
			remember_me: 'Remember me',
			enter: 'Enter',
			date_close: 'Close',
			date_prv: 'prev',
			date_nxt: 'next',
			date_today: 'Today',
			date_list_months: 'January,February,March,April,May,June,July,August,September,October,November,December',
			date_list_months_short: 'Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec',
			date_list_days_sun: 'Sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
			date_list_days_3ch: 'Sun,Mon,Tue,Wed,Thu,Fri,Sat',
			date_list_days_2ch: 'su,mo,tu,we,th,fr,sa',
			date_week: 'Wk'
		}
	}
};

/*******************************************************************
 * I18N FUNCTIONS (Prefixed by 'Tr', guess nlI18nInit is terrible)
 *******************************************************************/

/**
 * nlTrLoad ( callback onLoad )
 * Loads translations from the server and executes the callback
 */

function nlTrLoad ( onLoad ) {
	nlGetJson('/core-i18n/get.json')
	.success(function(json) {
		if (json.data.strings) {
			nlTr = json.data;
		} else {
			nlError(t('core/i18n_no_data'));
		}
		$('#nlHistoryButton').text(t('core/notifications'));
		$('#nlLogoutButton').text(t('core/logout'));
		onLoad();
	})
	.error(function(jqXHR, textStatus, errorThrown) {
		nlError(t('i18n_load_error'));
	});
}

/**
 * nlTrText ( string document )
 * Tries to replace all <t>keywords</t> in given string by
 * corresponding language strings
 */
 
function nlTrText ( str ) {
	var tre = /\<t\>([\w\/]+)\<\/t\>/gi;
	return str.replace(tre, nlTWrapper);
}

function nlTWrapper ( matched, keyword, offset, str ) {
	return t(keyword);
}

/**
 * t ('key')
 * t ('service/key')
 * t ('[service/]key', { subst1: 'some data', subst2: '42' })
 * 
 * Translates a string key into localized string. By default current
 * loaded service (nlService) is used as service, but you can specify one
 * as shown above. Named parameters are also supported:
 * 
 * nlTr.strings.core.there_are_crows = "There are %crows crows";
 * alert(t('core/there_are_crows',{crows:'over9000'}));
 */

function t ( key, args ) {
	if ( key && (typeof(key)=='string') ) {
		var service = 'core';
		var str = '';

		var sp = key.split('/');	// Is there a service?
		if ( typeof(sp[1]) === 'string' ) {
			service = sp[0];
			key = sp[1];
		}
		
		if (nlTr) {					// Trying to find the string
			if (nlTr.strings[service]) {
				if (typeof nlTr.strings[service][key] != 'undefined') {
					str = nlTr.strings[service][key];
				}
			}
			if (str === '') {
				if (nlTr.strings.core) {
					if (typeof nlTr.strings.core[key] != 'undefined') {
						str = nlTr.strings.core[key];
					}
				}
			}
		}
		
		if (str !== '') {			// Substitute args, if any
			if ( typeof(args)=='object' ) {
				for ( var arg in args ) {
					str = str.split('%'+arg).join(args[arg]);
				}
			}
			return str;
		} else {					// String not found. FAIL!
			var trMark = nlTr.lang+'('+service+'/'+key+')';
			
			if (typeof nlFailedStrings[trMark] == 'undefined') {
				nlFailedStrings[trMark] = 1;
				//if (nlTr.editor) {
					var placeholders = '';
					if ( typeof args == 'object' ) {
						for ( var arg in args ) {
							placeholders += ' %'+arg;
						}
					}
					nlDebug('<a href="javascript:void(0)" onclick="nlTrEdit(\''+service+'\',\''+key+'\',\''+placeholders+'\')">add translation</a> for '+trMark);
				//}
			}

			return trMark;
		}
	} else {
		return '';
	}
}

function nlTrEdit ( service, key, placeholders ) {
	$('#trLanguage').text(nlTr.lang);
	$('#trService').val(service);
	$('#trKeyword').val(key);
	$('#trPlaceholders').text(placeholders);
	$('#trTranslation').val('');
	$('#nlTrDialog').dialog('open');
	return false;
}

function nlTrSave () {
	var data = {
		lang: nlTr.lang,
		service: $('#trService').val(),
		keyword: $('#trKeyword').val(),
		value: $('#trTranslation').val()
	};
	nlPostJson('/core-i18n/create.json',data,'json')
	.success(function(json) {
		nlNotify(t('core/string_save_ok'));
	})
	.error(function(jqXHR, textStatus, errorThrown) {
		nlError(t('core/string_save_error'));
	})
	.complete(function() {
		$('#nlTrDialog').dialog('close');
	});
}

/*******************************************************************
 * LOGIN FUNCTIONS
 *******************************************************************/

/**
 * nlInitLogin ()
 * Initializes login buttons and form
 */

function nlInitLogin () {
	$('#nlButtonLogin').button({label:t('core/button_enter')});
	$('#nlLogoutButton')
		.click(function() {	nlLogout();	});
	$('#nlLoginTitle').text(t('core/login_title'));
	$('#nlLoginLabel').text(t('core/label_username'));
	$('#nlPasswordLabel').text(t('core/label_password'));
	$('#nlRememberLabel').text(t('core/label_remember'));
	$('#nlLoginHost').text('Nibelite V ('+window.location.host+')');
	$('#nlLoginDialogForm').submit(function() { 
		$.blockUI(
			{ 
				message: "<h2>"+t('request_sent')+"</h2>",
				overlayCSS:  {
					backgroundColor: '#fff',
					opacity: 1,
					cursor: 'pointer'
				},
				css: {
					border: 'none',
					cursor: 'pointer'
				}
					
			}
		); 
		nlGetJson('/core-portal/login.json', $(this).serialize())
		.complete(function() { 
			$.unblockUI(); 
		})
		.success(function(json) {
			if (json.data.id) {
				nlUser = json.data.user;
				nlUserId = json.data.id;
				nlAuthorized = true;
				nlNotify(t('you_are_logged',{username:nlUser}));
				nlShowUser();
				nlMenuLoad();
			} else {
				nlNotify(t('login_failed'));
				nlShowLogin(json.control.message);
			}
		})
		.error(function(jqXHR, textStatus, errorThrown) {
			nlError(textStatus);
			nlShowLogin(textStatus);
		});
		return false;
	}); 
}

/**
 * nlLoginCheck ( callback onLogged, callback onNotLogged )
 * Checks login status against the backend, firing callbacks
 */

function nlLoginCheck ( onLogged, onNotLogged ) {
	nlGetJson('/core-portal/check_login.json')
	.success(function (json) {
		if (json.data.id) {
			onLogged(json.data.login, json.data.id);
		} else {
			onNotLogged();
		}
	})
	.error(function(jqXHR, textStatus, errorThrown) {
		nlError(textStatus);
		onNotLogged();
	});
}

/**
 * nlLogout ()
 * Calls backend for logout
 */

function nlLogout () {
	nlGetJson('/core-portal/logout.json')
	.complete(function() {
		nlLoginCheck(
			function (login,uid) {
				nlUser = login;
				nlUserId = uid;
				nlAuthorized = true;
				nlError(t('logout_failed'));
			},
			function () {
				// Total cleanup!
				window.location.reload();
			}
		);
	});
}
 
/** 
 * nlShowLogin ( message )
 * Shows login screen with optional title. 
 * We need to block page totally, so 
 * blockUI used instead of the JQuery UI modal dialog
 */

function nlShowLogin ( msg ) {
	if (msg) {
		$('#nlLoginTitle').text(t('error')+': '+msg);
		$.growlUI( t('error'), msg, 3000, function () {
			$.blockUI({ 
				message: $('#nlLoginDialog'),
				overlayCSS:  {
					backgroundColor: '#fff',
					opacity: 1
				},
				css: {
					border: 'none',
					cursor: 'pointer'
				} 
			}); 
		});
	} else {
		$('#nlLoginTitle').text(t('log_in'));
		$.blockUI({ 
			message: $('#nlLoginDialog'),
			overlayCSS:  {
				backgroundColor: '#fff',
				opacity: 1
			}, 
			css: {
				border: 'none',
				cursor: 'pointer'
			}
		}); 
	}
}

/*******************************************************************
 * MENU AND SCREENS FUNCTIONS
 *******************************************************************/

/**
 * nlInitHash
 * Parse #hash/part of window url
 */

function nlInitHash () {
	var hash = window.location.hash;
	if (hash != '') {
		nlInitialHash = hash.split('/');
		nlInitialService = nlInitialHash[0];
		if (typeof nlInitialService == 'string') {
			nlInitialService = nlInitialService.substr(1);
		}
		nlInitialScreen = nlInitialHash[1];
	}
}

/** 
 * nlMenuLoad
 * Loads the main menu from the backend
 */
 
function nlMenuLoad () {
	nlGetJson('/core-portal/menu.json')
	.success(function (json) {
		if (json.data) {
			var items = [];
			$.each(json.data.menu,function (key,val) {
				items.push('<li><div class="ui-state-default nl-service-'+key+'" onclick="nlServiceLoad(\''+key+'\')"><span class="ui-icon ui-icon-triangle-1-e"></span>'+t(key+'/menu_main')+'</div><ul class="nl-service-'+key+'" style="display:none"><li>'+t('core/loading')+'</li></ul></li>');
			});
			$('#nlMenuContainer').html(items.join(''));
			if (nlInitialService) {
				nlServiceLoad(nlInitialService);
				nlInitialService = false;
			} else if (json.data['default']) {
				nlServiceLoad(json.data['default']);
			}
		} else {
			nlError(t('no_main_menu'));
		}
	});
}

/** 
 * nlServiceLoad ( moduleName )
 * Loads info about service <moduleName> from the backend
 * Populates the module menu and fills the title
 */

function nlServiceLoad (serviceName) {
	if ($('ul.nl-service-'+serviceName).hasClass('nl-state-loaded')) {
		// Service already loaded, just fix highlights
		$('#nlMenuContainer li div span').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
		$('#nlMenuContainer li ul').slideUp();
		$('ul.nl-service-'+serviceName).slideDown();
		$('div.nl-service-'+serviceName+' span').removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
	} else {
		nlGetJson('/'+serviceName+'/info.json')
		.success(function (json) {
			if (json.data) {

				var items = [];
				$.each(json.data.menu,function (key,val) {
					if( key.match(/\.php$/) ) {
						items.push('<li class="ui-state-hover nl-screen-'+key+'"><a href="/nibelite/control/'+key+'" target="_blank">'+t('menu/'+key)+'</a></li>');
					} else {
						items.push('<li class="ui-state-hover nl-screen-'+key+'" onclick="nlScreenOpen(\''+serviceName+'\',\''+key+'\')">'+t(serviceName+'/menuitem_'+key)+'</li>');
					}
				});
				$('ul.nl-service-'+serviceName).addClass('nl-state-loaded').html(items.join(''));
				nlNotify(t('service_load_ok',{service:serviceName}));
				
				nlMenuService = serviceName;
				
				// Fix highlights
				$('#nlMenuContainer li div span').removeClass('ui-icon-triangle-1-s').addClass('ui-icon-triangle-1-e');
				$('#nlMenuContainer li ul').slideUp();
				$('ul.nl-service-'+serviceName).slideDown();
				$('div.nl-service-'+serviceName+' span').removeClass('ui-icon-triangle-1-e').addClass('ui-icon-triangle-1-s');
				
				// Load defaults (if any)
				
				if (nlInitialScreen) {
					nlScreenOpen(serviceName,nlInitialScreen);
					nlInitialScreen = false;
				}
			} else {
				nlError(t('service_load_error',{service:serviceName,error:json.control.message}));
			}
		})
		.error(function(jqXHR, textStatus, errorThrown) {
			nlError(textStatus);
		});
	}
}

/** 
 * nlRegister ( event, handler )
 * Register a handler for new screen
 **/
function nlRegister ( event, handler ) {
	if (nlNewScreen!==false && nlNewService!==false) {
		nlScreens[nlNewService][nlNewScreen][event] = handler;
	} else {
		nlError('New* not set for handlers');
	}
}

/** 
 * nlScreenOpen ( serviceName,screenName )
 * Open a business view screen or load one
 **/

function nlScreenOpen (serviceName,screenName) {

	// Switch handler
	function nlSwitch (serv,scrn) {
		var switchFrom = nlScreens[nlService][nlScreen];
		var switchTo = nlScreens[serv][scrn];
		
		switchFrom.fadeOut(300, function () {
			switchTo.fadeIn(300, function () {
				nlService = serv;
				nlScreen = scrn;

				// Setting up module info
				$('#nlCrumbs span').html(
					' &gt;&nbsp;' + t(serv+'/menu_main') +
					' &gt;&nbsp;' + t(serv+'/menuitem_'+scrn)
				);
				
				// Fix highlights
				$('#nlMenuContainer li ul li').removeClass('ui-state-highlight');
				$('li.nl-screen-'+scrn).addClass('ui-state-highlight');
				
				if ( switchTo.onScreenOpen ) {
					switchTo.onScreenOpen();
				}
				nlNotify(t('core/screen_switched', {service:serv,screen:scrn}));
			});
		});
		window.location.hash = serv+'/'+scrn;
	}

	// Don't switch to itself
	if ( serviceName===nlService && screenName===nlScreen ) {
		nlSameClicks++;
		if (nlSameClicks > 3) {
			nlSameClicks = 0;
			nlNotify(t('core/click_click'));
		}
		return false;
	}
	
	nlSameClicks = 0;

	// Service screens isn't loaded yet? Ook!
	if ( !nlScreens[serviceName] ) {
		nlScreens[serviceName] = {};
	}

	// Checking and firing the close handler
	if (nlScreens[nlService][nlScreen].onScreenClose) {
		nlNotify(t('close_current_screen', {service:nlService,screen:nlScreen}));
		if ( !nlScreens[nlService][nlScreen].onScreenClose() ) {
			nlNotify(t('screen_closing_denied'));
			return false;
		}
	}

	// Looking for screen slot and creating empty one
	
	if ( !nlScreens[serviceName][screenName] ) {
		var newNode = document.createElement('div');
		newNode.id = serviceName+'-'+screenName;
		newNode.style.display = 'none';
		$(newNode).appendTo('#nlBody');
		nlScreens[serviceName][screenName] = $('#'+newNode.id);
		nlScreens[serviceName][screenName].notLoaded = true;
	}
	
	// Is our screen already loaded? It can be successfully loaded
	// before; the screen slot may be just created above or there
	// may be previous loading error.
	
	if ( nlScreens[serviceName][screenName].notLoaded ) {

		// Loading screen
		
		nlNotify(t('loading_screen', {service:serviceName,screen:screenName}));
		nlNewService = serviceName;
		nlNewScreen = screenName;
		var fileBase = 'screens/'+serviceName+'/'+screenName; 
		
		$.get(fileBase+'.html',{nocache:Math.random()})
		.success(function (data) {
			// Translate and set up
			nlScreens[serviceName][screenName].html('<div class="nlScreen">' + nlTrText(data) + '</div>');
			
			// Try to load corresponding JS
			$('<script>')
				.attr('type','text/javascript')
				.attr('src',fileBase+'.js')
				.appendTo('head'); 
			
			// Screen loaded ok
			nlScreens[serviceName][screenName].notLoaded = false;
			nlDebug(t('screen_load_ok', {service:serviceName,screen:screenName}));
			
			// Enhance widgets
			nlEnhanceInputs(nlScreens[serviceName][screenName]);
			
			// Firing registered events
			if (nlScreens[serviceName][screenName].onScreenLoad) {
				nlScreens[serviceName][screenName].onScreenLoad(serviceName,screenName);
			} else {
				setTimeout(
					"if(nlScreens["+serviceName+"]["+screenName+"].onScreenLoad)"+
					"{nlScreens["+serviceName+"]["+screenName+"].onScreenLoad();}",
					1000);
			}
			
			// Switching to initialized screen
			nlSwitch(serviceName,screenName);
		})
		.error(function() {
			// Screen not loaded. Try again later?
			nlError(t('error_loading_screen', {service:serviceName,screen:screenName}));
		});

	} else {
		
		// Just switching to existing screen
		
		nlSwitch(serviceName,screenName);
	}
}

/*******************************************************************
 * NOTIFICATIONS AND HISTORY
 *******************************************************************/

/** 
 * nlTime ()
 * returns a fancy timestamp
 **/

function nlTime () {
	var d = new Date();
	return '<span class="ui-state-disabled">'+d.toLocaleTimeString()+'</span>';
}

var notifyCount = 0;

/** 
 * nlNotify ( message )
 * pops up a fading notify message with on-click history of events
 **/

function nlNotify (notifyText, doNotShow) {
	notifyCount++;
	$('<div id="nlHistory'+notifyCount+'" class="nlBulb ui-state-default ui-corner-all" style="display:none"> <img src="/ico/information.png" class="icon" alt="" /> '+nlTime()+' '+notifyText+'</div>').appendTo('#nlNotify');
	if (!doNotShow) {
		$('#nlHistory'+notifyCount).slideDown('fast').fadeOut(5000);
	}
}

/** 
 * nlError ( message )
 * pops up a fading error message with on-click history of events
 * coded hard with copy&paste
 **/

function nlError (errorText) {
	notifyCount++;
	$('<div id="nlHistory'+notifyCount+'" class="nlBulb ui-state-error ui-corner-all" style="display:none"> <img src="/ico/exclamation.png" class="icon" alt="" /> '+nlTime()+' '+errorText+'</div>').appendTo('#nlNotify');
	$('#nlHistory'+notifyCount).slideDown('fast').delay(10000).fadeOut(5000);
}

/** 
 * nlShowHistory ()
 * pops up the notifications history dialog
 **/

function nlShowHistory () {
	var items = [];
	$('.nlBulb').each(function (i,el) {
		items.push('<li>'+$(el).html()+'</li>');
	});
	$('#nlHistoryDialogList').html(items.join(''));
	$('#nlHistoryDialogList li:odd').css('background-color','#d0e5f5');
	$('#nlHistoryDialog').dialog('open');
	return false;
}

/** 
 * nlInitHistory ()
 * Initializes history dialog
 **/

function nlInitHistory () {
	$('#nlHistoryButton').click(nlShowHistory);
	$('#nlNotify').click(nlShowHistory);
	
	$('#nlHistoryDialog').dialog({
		autoOpen: false,
		buttons: { "Ok": function() { $(this).dialog("close"); } },
		modal: true,
		width: '400px',
		position: "center",
		title: t('system_history')
	});

	$('#nlTrDialog').dialog({
		autoOpen: false,
		buttons: { "Ok": nlTrSave },
		modal: true,
		width: '400px',
		position: "center",
		title: t('add_translation')
	});
}

/*******************************************************************
 * WIDGETS
 *******************************************************************/

/**
 * nlShowUser()
 * Shows logged in user. Or nothing.
 */
function nlShowUser () {
	if (nlUser == 'admin') {
		nlTr.editor = 1;
	} else {
		nlTr.editor = 0;
	}
	$('#nlUser').text(nlUser);
}

/**
 * nlInitLayout()
 * Feng Shui magic
 */

function nlPx (val) {
	if (typeof val === 'string') {
		val = val.replace('px','');
		if ( parseInt(val) != NaN ) {
			return parseInt(val);
		}
	}
	return 0;
}

function nlInitLayout () {
	var wWidth = $(window).width();
	var wHeight = $(window).height();

	var nlMain = $('#nlMain');
	var mainVMargins = 2 * nlMainMargins; // nlMain.outerHeight(true) - nlMain.height();
	var mainHMargins = 2 * nlMainMargins; // nlMain.outerWidth(true) - nlMain.width();	
	var mainTopDelta = nlMainMargins; // nlPx(nlMain.css('marginTop')) + nlPx(nlMain.css('paddingTop')) + nlPx(nlMain.css('borderTopWidth'))
	var mainLeftDelta = nlMainMargins; // nlPx(nlMain.css('marginLeft')) + nlPx(nlMain.css('paddingLeft')) + nlPx(nlMain.css('borderLeftWidth'))

	var body = $('body');
	var bodyTop = nlBodyMargins; // nlPx(body.css('marginTop')) + nlPx(body.css('paddingTop')) + nlPx(body.css('borderTopWidth'));
	var bodyLeft = nlBodyMargins; // nlPx(body.css('marginLeft')) + nlPx(body.css('paddingLeft')) + nlPx(body.css('borderLeftWidth'));
	var bodyBottom = nlBodyMargins; // nlPx(body.css('marginBottom')) + nlPx(body.css('paddingBottom')) + nlPx(body.css('borderBottomWidth'));
	var bodyRight = nlBodyMargins; // nlPx(body.css('marginRight')) + nlPx(body.css('paddingRight')) + nlPx(body.css('borderRightWidth'));
	var headHeight = nlHeadHeight; // $('#nlHead').outerHeight(true);

	// Resizing main panel (with menu and workspace)
	var mainWidth = wWidth - bodyLeft - bodyRight - mainHMargins;
	var mainHeight = wHeight - bodyTop - bodyBottom - headHeight - mainHMargins;
	nlMain.width(mainWidth);
	nlMain.height(mainHeight);
	nlMain.css('left',bodyLeft+'px');
	nlMain.css('top',(bodyTop+headHeight)+'px');

	var absTop = bodyTop + headHeight + mainTopDelta;
	var absLeft = bodyLeft + mainLeftDelta;

	// Resizing menu
	// Positioning: relative 0:0 always
	var nlMc = $('#nlMenuContainer');
	nlMc.height( mainHeight - 2 * nlMenuMargins );
	nlMc.css('top',absTop+'px');
	nlMc.css('left',absLeft+'px');

	// Resizing splitter bar vertically
	var nlSplit = $('#nlSplitter');
	nlSplit.height( mainHeight - 2 * nlSplitMargins );
	nlSplit.css('top',absTop+'px');

	var nlB = $('#nlBody');
	nlB.height( mainHeight - 2 * nlBMargins );
	nlB.css('top',absTop+'px');
	
	if ( nlMenuHidden ) {
		nlSplit.css('left',absLeft+'px');
		nlB.css('left',(absLeft+nlSplitOuterWidth)+'px');
		nlB.width(mainWidth - nlSplitOuterWidth - nlBMargins * 2);
	} else {
		nlSplit.css('left',(absLeft+nlMenuOuterWidth)+'px');
		nlB.css('left',(absLeft+nlMenuOuterWidth+nlSplitOuterWidth)+'px');
		nlB.width(mainWidth - nlSplitOuterWidth - nlMenuOuterWidth - nlBMargins * 2);
	}
	
}

function nlToggleMenu () {
	if (nlMenuHidden) {
		$('#nlMenuContainer').show(0);
		$('#nlSplitter div').removeClass('invert');
		nlMenuHidden = false;
		
	} else {
		$('#nlMenuContainer').hide(0);
		$('#nlSplitter div').addClass('invert');
		nlMenuHidden = true;
	}
	nlInitLayout();
}

/**
 * nlInitDateTime()
 * Initializing datePicker and timePicker
 */
 
function nlInitDateTime() {
	$.datepicker.setDefaults({
		closeText: t('core/date_close'),
		prevText: '&#x3c;'+t('core/date_prv'),
		nextText: t('core/date_nxt')+'&#x3e;',
		currentText: t('core/date_today'),
		monthNames: t('core/date_list_months').split(','),
		monthNamesShort: t('core/date_list_months_short').split(','),
		dayNames: t('core/date_list_days_sun').split(','),
		dayNamesShort: t('core/date_list_days_3ch').split(','),
		dayNamesMin: t('core/date_list_days_2ch').split(','),
		weekHeader: t('core/date_week'),
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: '',
		dateFormat: 'yy-mm-dd',
		constrainInput: false,
		duration: 0
	});
	$.timepicker.setDefaults({
		timeOnlyTitle: t('core/time_choose'),
		timeText: t('core/time_text'),
		hourText: t('core/time_hours'),
		minuteText: t('core/time_minutes'),
		secondText: t('core/time_seconds'),
		millisecText: t('core/time_millis'),
		currentText: t('core/time_now'),
		closeText: t('core/time_close'),
		ampm: false
	});
}

/**
 * nlEnhanseInputs ( DOM context )
 * Add UI modifiers to conventional inputs in context
 */

function nlEnhanceInputs( context ) {
	$('.datepicker',context).each(function() {
		$(this).datepicker();
	});
	$('.datetimepicker',context).each(function() {
		$(this).datetimepicker();
	});
}

function nlAddToolbar( serviceName, screenName ) {
	var toolbar = $('<div>');
	toolbar
		.attr('id','toolbar-'+serviceName+'-'+screenName)
		.addClass('ui-widget')
		.addClass('nlScreenToolbar')
		.prependTo(nlScreens[serviceName][screenName]);
	return toolbar;
}

/*******************************************************************
 * HOMEBREW JQUERY EXTENSIONS
 *******************************************************************/

(function( $ ){

	/**
	 * $.fn.getObject()
	 * Get form data as JavaScript object (array with names:values)
	 * 
	 * $(...) may represent particular $('form') or a collection
	 * of elements, like $('.serializedInput')
	 */

	$.fn.getObject = function() {
		var obj = {};
		var pairs = $(this).serializeArray();
		for ( var i=0; i<pairs.length; i++) {
			obj[pairs[i]['name']] = pairs[i]['value'];
		}
		$(this).find("input[type=checkbox]").each(function(){
			var t = $(this);
			if(t.is(':checked')) {
				obj[t.attr('name')] = t.val();
			} else {
				obj[t.attr('name')] = false;
			}
		});
		return obj;
	};
	
	/**
	 * $.fn.setObject( obj )
	 * Set form or collection data (values of various inputs) from JS object
	 */
	
	$.fn.setObject = function( obj ) {
		this.each( function () {
			for (key in obj) {
				$('[name="'+key+'"]',this).each( function () {
					var jqThis = $(this);
					if (jqThis.hasClass('datepicker')) {
						jqThis.datepicker('setDate',obj[key].substr(0,10));
					} else if (jqThis.hasClass('datetimepicker')) {
						jqThis.datepicker('setDate',obj[key].substr(0,16));
					} else if ((jqThis.attr("type") == 'checkbox')) {
						if (jqThis.val() == obj[key]) {
							jqThis.attr('checked', 'checked');
						} else {
							jqThis.attr('checked', null);
						}
					} else if ((jqThis.attr("type") == 'radio')) {
						if (jqThis.val() == obj[key]) {
							jqThis.attr('checked', 'checked');
						} else {
							jqThis.attr('checked', null);
						}
					} else {
						$(this).val(obj[key]);
					}
				});
			}
		});
	};

	/**
	 * $.fn.blinkField( name )
	 * Animate blinking on the input field with corresponding name and focus it
	 */
	
	$.fn.blinkField = function( name ) {
		this.each( function () {
			$('[name="'+name+'"]',this)
				.stop(true,true)
				.fadeTo(150,0.2)
				.fadeTo(150,1)
				.fadeTo(150,0.2)
				.fadeTo(150,1)
				.fadeTo(150,0.2)
				.fadeTo(200,1,function () { $(this).focus(); });
		});
	};
	
	/**
	 * $.fn.toolbar( buttons )
	 * buttons = [ { caption: 'A button', click: 'someFunction' }, { caption: '...', click: function () {...} } ]
	 */
	 
	$.fn.toolbar = function ( buttons ) {
		for ( var i=0; i<buttons.length; i++) {
			notifyCount++;
			if (typeof buttons[i]['span'] === 'string') {
				var span = $('<span id="span'+notifyCount+'" class="nlToolbarSpan" />');
				span.html(buttons[i]['span']);
				span.appendTo(this);
			} else if (buttons[i]['separator']) {
				var span = $('<span id="span'+notifyCount+'" class="nlToolbarSeparator" />');
				span.appendTo(this);
			} else if (typeof buttons[i]['caption'] === 'string') {
				var caption = buttons[i]['caption'];
				if (typeof buttons[i]['icon'] === 'string') {
					caption = '<img src="'+buttons[i]['icon']+'" alt="" class="icon" />&nbsp;&nbsp;'+caption;
				}
				var btn = $('<button id="btn'+notifyCount+'">'+caption+'</button>');
				btn.appendTo(this);
				btn.button();
				var clk = buttons[i].click;
				var clktype = typeof buttons[i].click;
				if (clktype === 'function') {
					btn.click(clk);
				} else if (clktype === 'string') {
					if (typeof window[clk] === 'function') {
						btn.click(window[clk]);
					} else {
						nlDebug('toolbar: No function named ['+clk+'] for button ['+buttons[i].caption+']');
					}
				} else if (clktype === 'undefined') {
					// Zen button. Press to nothing.
				} else {
					nlDebug('toolbar: Can\'t register ['+clktype+'] as click handler for button ['+buttons[i].caption+']');
				}
			}
		}
	};

	
})( jQuery );

/**
 * tableSelected( dataTables object )
 * Returns an array of selected rows (DOM <tr> nodes)
 */

function tableSelected( oTableLocal ) {
    var aReturn = new Array();
    var aTrs = oTableLocal.fnGetNodes();
    for ( var i=0 ; i<aTrs.length ; i++ ) {
        if ( $(aTrs[i]).hasClass('ui-state-highlight') ) {
            aReturn.push( aTrs[i] );
        }
    }
    return aReturn;
}

/**
 * tableSelectRow( event )
 * Onclick handler for dataTables cells
 */

function tableSelectRow( event ) {
	$('tr',event.target.parentNode.parentNode).each(function (){
		$(this).removeClass('ui-state-highlight');
	});
	$(event.target.parentNode).addClass('ui-state-highlight');
	event.preventDefault();
}

/**
 * tableDefaults ( prefs obj )
 * generates dataTables init object customized for Nibelite
 */ 

function tableDefaults( obj ) {
	return mergeDefaults({
		// Features 
		bAutoWidth:			false,
		bJQueryUI: 			false,	// We NOT rely on JQUI eyecandy
		bLengthChange:		false,	// No "10-50-100" dropdown
		
		sPaginationType:	'full_numbers',	// Nice pagination
		iDisplayLength:		20,		// 20 users per page
		
		// AJAX init. Note fnServerData - we are shifting initial data to data.data
		fnServerData:		function ( url, data, callback ) {
								$.getJSON( url, data, function (data, textStatus, jqXHR) {
									callback( data.data, textStatus, jqXHR );
								} );
							},
		bServerSide:		true,	// Filtering, sorting and paging are server-side
		oLanguage:			{
								oPaginate:	{
									sFirst: t('core/dt_pager_first'),
									sLast: t('core/dt_pager_last'),
									sNext: t('core/dt_pager_next'),
									sPrevious: t('core/dt_pager_prev'),
								},
								sEmptyTable: t('core/dt_empty_table'),
								sInfo: t('core/dt_info'),
								sInfoEmpty: t('core/dt_info_empty'),
								sInfoFiltered: t('core/dt_info_filtered'),
								sInfoThousands: t('core/dt_info_thousands'),
								sLengthMenu: t('core/dt_length_menu'),
								sLoadingRecords: t('core/dt_loading'),
								sProcessing: t('core/dt_processing'),
								sSearch: t('core/dt_search'),
								sZeroRecords: t('core/dt_empty_filtered')
							}
	},
	obj);
}

/**
 * tableCellButtons( Array buttons )
 * buttons = [ { caption: 'A button', click: 'someFunctionName' }, { caption: '...', click: 'someFunc...'} ]
 * Data row number will be passed to someFunction
 * Use dataTablesObject.fnGetData(num) to retrieve row data
 */
 
function tableCellButtons ( buttons ) {
	return {	
		bSortable: false,
		bSearchable: false,
		bUseRendered: false,
		sType: 'string',
		sDefaultContent: 'functions',
		fnRender: function (r) {
			var row = r.iDataRow;
			var html = '<div style="text-align:right">';
			for ( var i=0; i<buttons.length; i++) {
				if (typeof(buttons[i]['check'])==='number') {
					if (r.aData[buttons[i]['check']] != buttons[i]['against']) {
						continue;
					}
				}
				html += 
					'<a href="javascript:void(0);" onclick="'
					+buttons[i].click+'('
					+row+')" class="rowButton">'
					+buttons[i].caption+'</a>';
			}
			html += '</div>';
			return html;
		}
	}
}

function tableCellNumeric ( obj ) {
	return mergeDefaults({
		bSearchable: false,
		sType: 'numeric'},
		obj);
}

function tableCellDate ( obj ) {
	return mergeDefaults({
		bSearchable: false,
		sType: 'date',
		fnRender: function ( rnd ) {
			var data = rnd.aData[rnd.iDataColumn];
			if ( typeof(data) === 'string' ) {
				return data.substr(0,19);
			} else {
				return data;
			}
		}},
		obj);
}

function tableCellString ( obj ) {
	return mergeDefaults({
		sType: 'string'},
		obj);
}

function tableCellText ( obj ) {
	return mergeDefaults({
		bSortable: false,
		sType: 'string'},
		obj);
}

function tableCellBoolean ( trueCap, falseCap, obj ) {
	if (!trueCap) trueCap = 'YES';
	if (!falseCap) falseCap = 'NO';
	return mergeDefaults({
		bSearchable: false,
		bUseRendered: false,
		fnRender: function ( rnd ) {
			if ( rnd.aData[rnd.iDataColumn] ) {
				return trueCap;
			} else {
				return falseCap;
			}
		}},
		obj);
}

function tableCellEnum ( mapping, obj ) {
	return mergeDefaults({
		bSearchable: false,
		bUseRendered: false,
		fnRender: function ( rnd ) {
			var data = rnd.aData[rnd.iDataColumn];
			if ( typeof(mapping) === 'object' ) {
				if ( typeof(mapping[data]) === 'string' ) {
					return mapping[data];
				} else {
					return data;
				}
			}
		}},
		obj);
}

function defaultDialog ( okHandler, obj ) {
	return mergeDefaults({
		autoOpen: false,
		buttons: { 
			"Ok": function() { 
				if ( okHandler(this) ) {
					$(this).dialog("close"); 
				}
			},
			"Cancel": function() { 
				$(this).dialog("close"); 
			}
		},
		modal: true,
		width: '480px',
		position: "center"
	},
	obj);
}


/*******************************************************************
 * LITTLE SANTA HELPERS
 *******************************************************************/

function nlGetJson (url, data) {
	if (typeof data == 'undefined') {
		data = {};
	}
	data.nocache = Math.random();
	return $.getJSON(PREFIX + url,data);
}

function nlPostJson (url, data) {
	return $.post(PREFIX + url,data,'json');
}

function nlDebug (msg) {
	nlNotify(msg,true);
}
 
function mergeDefaults ( defaults, obj ) {
	if (obj) {
		for (var key in obj) {
			defaults[key] = obj[key];
		}
	}
	return defaults;
}

Array.prototype.has = function (val) {
	for ( var i=0;i<this.length;i++ ) {
		if (this[i]==val) return true;
	}
	return false;
}

Array.prototype.mapArray = function (keys) {
	var obj = {}
	for ( var i=0;i<keys.length;i++ ) {
		obj[keys[i]] = this[i];
	}
	return obj;
}

/**
 * diffEx ( object orig, object changed, array excl )
 * Deletes props of changed that aren't listed in excl and has values identical to orig props
 */
function diffEx ( orig, changed, excl ) {
	for (var key in changed) {
		if (!excl.has(key) && (changed[key] == orig[key])) {
			delete changed[key];
		}
	}
	return changed;
}

/*******************************************************************
 * VOID MAIN (VOID)
 *******************************************************************/

$(function(){
	
	nlScreens.core.blank = $('#core-blank');
	$('#nlSplitter').click(nlToggleMenu);
	nlInitHash();
	nlInitLayout();
	$(window).resize(nlInitLayout);

	nlTrLoad(function () {
		nlNotify(t('i18n_loaded'));
		nlInitDateTime();
		nlInitHistory();
		nlInitLogin();
		nlLoginCheck(
			function (login,uid) {
				nlUser = login;
				nlUserId = uid;
				nlAuthorized = true;
				nlShowUser();
				nlMenuLoad();
			},
			function () {
				nlShowUser();
				nlShowLogin(false);
			}
		);
	});

});
