	var lastXhr;
	
	nlRegister('onScreenLoad', function (serviceName,screenName) {
		transTable = $('#nlTransTable').dataTable(tableDefaults({
			aoColumns: 			[  
									tableCellNumeric(), // id
									tableCellString(), // lang
									tableCellString(), // service
									tableCellString(), // keyword
									tableCellText(), // value
									tableCellButtons([
										{ caption: 'Edit', click: 'transEditRow' },
										{ caption: 'Delete', click: 'transDeleteRow' }
									])
								],
			aaSorting:			[[1,'asc'],[2,'asc'],[3,'asc']],	// Default sort: lang,service,keyword
			sAjaxSource:		PREFIX+'/core-i18n/list.json'
		})).fnSetFilteringDelay(1000);

		nlAddToolbar(serviceName,screenName).toolbar([
			{ caption: t('i18n/create'), click: 'transCreateNew', icon: '/ico/comment_add.png' },
			{ separator: true },
			{ span: t('i18n/language')+':' },
			{ span: '<input type="text" name="lang" id="trExportLang" />' },
			{ span: t('i18n/service')+':' },
			{ span: '<input type="text" name="service" id="trExportService" />' },
			{ caption: t('i18n/export_to_sql'), click: 'transExportFile', icon: '/ico/database_save.png' }
		]);

		$('#transEditDialog').dialog(defaultDialog(transEditSave,{ title: 'Edit String' }));
		
		$('#teService').autocomplete({
			minLength: 2,
			source: function( request, response ) {
				nlGetJson('/core-i18n/services.json', request)
				.success(function( data, status, xhr ) {
					response( data.data );
				});
			}
		});
		$('#teKeyword').autocomplete({
			minLength: 2,
			source: function( request, response ) {
				nlGetJson('/core-i18n/keywords.json', request)
				.success(function( data, status, xhr ) {
					response( data.data );
				});
			}
		});
		$('#trExportService').autocomplete({
			minLength: 2,
			source: function( request, response ) {
				nlGetJson('/core-i18n/services.json', request)
				.success(function( data, status, xhr ) {
					response( data.data );
				});
			}
		});
		$('#trExportLang').autocomplete({
			minLength: 1,
			source: function( request, response ) {
				nlGetJson('/core-i18n/languages.json', request)
				.success(function( data, status, xhr ) {
					response( data.data );
				});
			}
		});
	});
	
	function transDownload (url) {
		var iframe;
		iframe = document.getElementById("hiddenDownloader");
		if (iframe === null) {
			iframe = document.createElement('iframe');  
			iframe.id = "hiddenDownloader";
			iframe.style.visibility = 'hidden';
			document.body.appendChild(iframe);
		}
		iframe.src = url;   
	}
	
	function transExportFile () {
		var url = PREFIX + '/core-i18n/export.file?lang='+$('#trExportLang').val()+'&service='+$('#trExportService').val();
		transDownload(url);		
	}

	function transEditRow ( rowId ) {
		var row = transTable.fnGetData(rowId);
		if (row) {
			var data = row.mapArray(['id','lang','service','keyword','value']);
			$('#transEditForm').data('original',data);
			$('#transEditForm').setObject(data);
			$('#transEditDialog').dialog('option','title',t('i18n/edit_translation'));
			$('#transEditDialog').dialog('open');
		}
	}
	
	function transDeleteRow ( rowId ) {
		var row = transTable.fnGetData(rowId);
		if (row) {
			var data = row.mapArray(['id','lang','service','keyword','value']);
			var shui = data.lang+'['+data.service+'/'+data.keyword+']';
			if ( confirm("Are you sure you want to delete string "+shui+"?") ) {
				nlPostJson('/core-i18n/delete.json',{id:data.id})
					.complete(function() {
						transTable.fnDraw(false);
					});
			}
		}
	}
	
	function transCreateNew () {
		var data = {
			'id':'new', 
			'lang':nlTr.lang, 
			'service':'core', 
			'keyword':'', 
			'value':''
		};
		$('#transEditForm').data('original',data);
		$('#transEditForm').setObject(data);
		$('#transEditDialog').dialog('option','title',t('i18n/add_translation'));
		$('#transEditDialog').dialog('open');
		$('#transEditForm').blinkField('keyword');
	}

	function transEditError ( msg, fieldName ) {
		if (msg) {
			$('#transEditError').stop(true,true).hide(0).html(msg).fadeIn('fast').fadeOut(5000);
		}
		if (fieldName) {
			$('#transEditForm').blinkField(fieldName);
		}
	}
	
	function transEditSave () {
		var data = $('#transEditForm').getObject();
		var orig = $('#transEditForm').data('original');
		var excl = ['id','lang','service'];
		
		if (data.lang === '' || data.lang.length != 2) {
			transEditError(t('i18n/enter_lang_code'),'lang');
			return false;
		}
		if (data.service === '') {
			data.service = 'core'; // stay silent: core is master default
		}
		if (data.keyword === '') {
			transEditError(t('i18n/enter_keyword'),'keyword');
			return false;
		}
		var shui = data.lang+'['+data.service+'/'+data.keyword+']';
		data = diffEx(orig,data,excl);
		
		var ajaxUrl = '/core-i18n/update.json';
		var msgCommit = t('i18n/string_update_ok',{str:shui});
		var msgReject = t('i18n/string_update_error',{str:shui});
		if (data.id==='new') {
			delete data['id'];
			ajaxUrl = '/core-i18n/create.json';
			msgCommit = t('i18n/string_create_ok',{str:shui});
			msgReject = t('i18n/string_create_error',{str:shui});
		}
		
		nlPostJson(ajaxUrl,data)
			.success(function(json) {
				if (json.data.aData[0]) {
					nlNotify(msgCommit);
				} else {
					nlError(msgReject);
				}
			})
			.error(function(jqXHR, textStatus, errorThrown) {
				nlError(msgReject);
			})
			.complete(function() {
				transTable.fnDraw(false);
			});
		
		return true;
	}
