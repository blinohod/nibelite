nlRegister('onScreenLoad', function (serviceName,screenName) {
	// id src_app_id src_app_name src_addr dst_app_id dst_app_name dst_addr date_received msg_status msg_type msg_body
	messagesTable = $('#nlMessagesTable').dataTable(tableDefaults({
		aoColumns: 			[  
								tableCellNumeric(),					// id
								
								tableCellNumeric({bVisible:false}),	// src_app_id 
								tableCellString(),					// src_app_name
								tableCellString(),					// src_addr
								
								tableCellNumeric({bVisible:false}),	// dst_app_id 
								tableCellString(),					// dst_app_name
								tableCellString(),					// dst_addr
								
								tableCellDate(), 					// date_received
								tableCellString(),					// msg_status
								tableCellString(),					// msg_type
								tableCellText(),					// msg_body
								tableCellButtons([
									{ caption: t('messages/reply'), click: 'messagesReply' }
								])
							],
		aaSorting:			[[0,'desc']],	// Default sort: id desc,
		sAjaxSource:		PREFIX+'/core-messages/list.json'
	})).fnSetFilteringDelay(1000);
	
	nlAddToolbar(serviceName,screenName).toolbar([
		{ caption: t('messages/emulate'), click: 'messagesCreate', icon: '/ico/phone_sound.png' }
	]);
	
	$('#nlMessagesTable_filter').before('<div id="messagesToolbar" style="float:left;width:auto"></div>');
	$("div#messagesToolbar").toolbar([
		{ span: '<select name="messagesSrcAppId" id="messagesSrcAppId" style="width:8em"><option value="" selected="1">'+t('messages/all_channels')+'</option></select>' },
		{ span: '<img src="/ico/arrow_right.png" alt="" />' },
		{ span: '<select name="messagesDstAppId" id="messagesDstAppId" style="width:8em"><option value="" selected="1">'+t('messages/all_channels')+'</option></select>' },
		{ separator: true },
		
		{ span: '<img src="/ico/calendar_view_day.png" alt="" />' },
		{ span: '<input type="text" name="messagesDateReceived" id="messagesDateReceived" value="" size="10" />' },
		{ separator: true },
		
		{ span: t('messages/th_msg_status') },
		{ span: '<select name="messagesMsgStatus" id="messagesMsgStatus" style="width:7em"><option value="" selected="1">'+t('messages/status_any')+'</option><option value="fail">'+t('messages/status_fail')+'</option><option value="wait">'+t('messages/status_wait')+'</option><option value="ok">'+t('messages/status_ok')+'</option></select>' },
		{ separator: true }
	]);

	var fSrc = $('#messagesSrcAppId').change(function() { messagesTable.fnFilter($(this).val(),1,false,false,false); });
	var fDst = $('#messagesDstAppId').change(function() { messagesTable.fnFilter($(this).val(),4,false,false,false); });
	var selFormS = $('#messagesFormSrcAppId');
	var selFormD = $('#messagesFormDstAppId');
	
	nlGetJson('/core-messages/channels.json')
	.success(function(data){
		if (typeof data.data !== 'undefined') {
			$.each(data.data, function(key, value) {
				$('<option />', {'value':value['id'],'html':value['name']}).appendTo(fSrc);
				$('<option />', {'value':value['id'],'html':value['name']}).appendTo(fDst);
				$('<option />', {'value':value['id'],'html':value['name']}).appendTo(selFormS);
				$('<option />', {'value':value['id'],'html':value['name']}).appendTo(selFormD);
			});
		}
	});

	$('#messagesDateReceived').datepicker({
		onClose: function(dateText, inst) { messagesTable.fnFilter(dateText,7,false,false,false); }
	});
	
	$('#messagesMsgStatus').change(function() { messagesTable.fnFilter($(this).val(),8,false,false,false); });
	
	/* var fMsgType = $('#messagesMsgType').change(function() { messagesTable.fnFilter($(this).val(),9,false,false,false); });
	nlGetJson('/core-messages/types.json')
	.success(function(data){
		if (typeof data.data !== 'undefined') {
			$.each(data.data, function(key, value) {
				$('<option />', {'value':value,'html':value}).appendTo(fMsgType);
			});
		}
	}); */
	
	$('#messagesDialog').dialog(defaultDialog(messagesSend,{ title: t('messages/send_sms') }));
	
	window.setInterval(messagesReload,30000);
});

function messagesReload() {
	var oSettings = messagesTable.fnSettings();
    if (oSettings._iDisplayStart == 0) {
		messagesTable.fnDraw(false);
	}
}

function messagesReply(rowId) {
	var row = messagesTable.fnGetData(rowId);
	// id src_app_id src_app_name src_addr dst_app_id dst_app_name dst_addr date_received msg_status msg_type msg_body
	if (row) {
		var data = {
			src_app_id:row[4],
			dst_app_id:row[1],
			src_addr:row[6],
			dst_addr:row[3],
			msg_body:''
		};
		$('#messagesForm').setObject(data);
		$('#messagesDialog').dialog('open');
	}
}

function messagesCreate() {
	var data = {
		src_app_id:0,
		dst_app_id:0,
		src_addr:'',
		dst_addr:'',
		msg_body:''
	};
	$('#messagesForm').setObject(data);
	$('#messagesDialog').dialog('open');
}

function messagesSend() {
	var data = $('#messagesForm').getObject();
	nlNotify(t('messages/sending_sms'));
	nlPostJson('/core-messages/send.json',data)
	.success(function(json) {
		if (json.control.status !== 'error') {
			nlNotify(t('messages/sms_send_ok'));
			$('#messagesDialog').dialog('close');
			messagesTable.fnDraw(false);
		} else {
			nlError(t('messages/sms_send_error'));
		}
	})
	.error(function(jqXHR, textStatus, errorThrown) {
		nlError(t('messages/sms_server_error'));
	});
}