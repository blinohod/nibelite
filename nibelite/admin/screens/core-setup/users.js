var usersTActive = $('#usersTActive').html();
var usersTPassive = $('#usersTPassive').html();
	
nlRegister('onScreenLoad', function (serviceName,screenName) {
	usersTable = $('#nlUsersTable').dataTable(tableDefaults({
		aoColumns: 			[  
								tableCellNumeric(), 
								tableCellString(), 
								tableCellBoolean(usersTActive,usersTPassive),
								{ bVisible: false },
								{ bVisible: false },
								tableCellString(), 
								tableCellString(), 
								tableCellText(), 
								tableCellButtons([
									{ caption: t('users/btn_edit'), click: 'usersEditRow' }
								])
							],
		aaSorting:			[[2,'desc'],[1,'asc']],	// Default sort: active desc, login asc
		sAjaxSource:		PREFIX+'/core-users/list.json'
	})).fnSetFilteringDelay(1000);

	nlAddToolbar(serviceName,screenName).toolbar([
		{ caption: t('users/btn_add'), click: 'usersCreateNew', icon: '/ico/user_add.png' },
	]);

	$('#usersEditDialog').dialog(defaultDialog(usersEditSave,{ title: t('users/dialog_edit') }));
});

function usersEditRow ( rowId ) {
	var row = usersTable.fnGetData(rowId);
	if (row) {
		var data = row.mapArray(['id','login','active','created','expire','name','email','descr']);
		data.newpass1 = '';
		data.newpass2 = '';
		
		$('#usersEditForm').data('original',data);
		$('#usersEditForm').setObject(data);

		$('#usersEditChangePass').css('display','none');
		
		usersLoadGroups(data.id);
		$('#usersEditDialog').dialog('option','title',t('users/dialog_edit'));
		$('#usersEditDialog').dialog('open');
		$('#usersEditError').hide(0);
	}
}

function usersCreateNew () {
	var data = {
		'id':'new', 
		'login':'', 
		'active':1, 
		'created':'', 
		'expire':'', 
		'name':'', 
		'email':'', 
		'descr':'',
		'newpass1':'',
		'newpass2':''
	};
	$('#usersEditForm').data('original',data);
	$('#usersEditForm').setObject(data);

	$('#usersEditGroups').addClass('ui-state-disabled');
	$('#usersEditGroups').html('<center>'+t('users/groups_not_allowed')+'</center>');
	$('#usersEditChangePass').css('display','block');
	$('#usersEditDialog').dialog('option','title',t('users/dialog_create'));
	$('#usersEditDialog').dialog('open');
	$('#usersEditError').hide(0);
	$('#usersEditForm').blinkField('login');
}

function usersEditError ( msg, fieldName ) {
	if (msg) {
		$('#usersEditError').html(msg).fadeIn('fast');
	}
	if (fieldName) {
		$('#usersEditForm').blinkField(fieldName);
	}
}

function usersEditSave () {
	var data = $('#usersEditForm').getObject();
	var orig = $('#usersEditForm').data('original');
	var excl = ['id'];
	var user = data.login;
	
	if (user === '') {
		usersEditError(t('users/login_empty'),'login');
		return false;
	}
	if (data.newpass1!=='' || data.newpass2!=='') {
		if (data.newpass1 !== data.newpass2) {
			usersEditError(t('users/password_mismatch'),'newpass1');
			return false;
		}
		data['password'] = data.newpass1;
	}
	
	delete data['newpass1'];
	delete data['newpass2'];
	
	if (data.expire==='') {
		delete data['expire'];
	}
	
	data = diffEx(orig,data,excl);
	
	var ajaxUrl = '/core-users/update.json';
	var msgCommit = t('users/update_ok',{user:user});
	var msgReject = t('users/update_error',{user:user});
	if (data.id==='new') {
		delete data['id'];
		ajaxUrl = '/core-users/create.json';
		msgCommit = t('users/create_ok',{user:user});
		msgReject = t('users/create_error',{user:user});
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
			usersTable.fnPageChange('first');
		});
	
	return true;
}

function usersChangeGroup (userId,groupId,remove) {
	$('#usersEditGroups').addClass('ui-state-disabled');
	nlGetJson('/core-users/changegroup.json',{user_id:userId,group_id:groupId,remove:remove})
		.complete(function(json) {
			usersLoadGroups(userId);
		});
}

function usersLoadGroups (userId) {
	$('#usersEditGroups').removeClass('ui-state-disabled');
	$('#usersEditGroups').html('');

	nlGetJson('/core-users/groups.json',{id:userId})
		.success(function(json) {
			if (json.control.status !== 'error') {
				var groups = json.data;
				for (var i=0;i<groups.length;i++) {
					var el = $('<div>');
					el.attr('id','usersGroup-'+groups[i].id);
					el.css('cursor','pointer');
					el.addClass(groups[i].mine ? 'ui-state-highlight' : 'ui-state-default');
					$('<img>')
						.attr('src',groups[i].mine ? '/ico/tick.png' : '/ico/link.png')
						.addClass('icon')
						.appendTo(el);
					$('<span>')
						.html('&nbsp;'+groups[i].group_name)
						.appendTo(el);
					el.click( {userId:userId,groupId:groups[i].id,remove:groups[i].mine}, function (event) {
						usersChangeGroup(event.data.userId,event.data.groupId,event.data.remove);
					});
					el.appendTo($('#usersEditGroups'));			
				}
			} else {
				nlError(json.control.message);
			}
		})
		.error(function(jqXHR, textStatus, errorThrown) {
			nlError(t('users/groups_error'));
		});
}

