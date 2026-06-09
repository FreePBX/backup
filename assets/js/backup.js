//put all document ready stuff here... One listener to rule them all
$(document).ready(function () {
	toggle_warmspare();

	if($('#uploadrestore').length){
		var dz = new Dropzone("#uploadrestore",{
			url: `${FreePBX.ajaxurl}?module=backup&command=uploadrestore`,
			chunking: true,
			forceChunking: true,
			maxFiles: 1,
			maxFilesize: null,
			previewsContainer: false
		});
		dz.on("sending",function() {
			$("#uploadprogress").addClass('active');
			$("#uploadrestore").html(_("Uploading...")+'<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>')
		})
		dz.on('success', function(file){
			var ret = file.xhr.response || "{}";
			var jres = JSON.parse(ret);
			if(jres.md5.length){
				window.location = `?display=backup&view=processrestore&type=local&fileid=${jres.md5}`;
			}
		});
		dz.on('processing', function() {
			$("#uploadrestore").html(_("Processing...")+'<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>')
		})
		dz.on('uploadprogress', function(event,progress,total){
			if(progress < 100) {
				$("#uploadprogress").text(progress.toFixed(2)+'%');
				$("#uploadprogress").css('width', `${progress}%`);
			}
		});
	}

	$("#runrestore").click(function(e) {
		e.stopPropagation();
		e.preventDefault();
		let skip = null;
		if ($("#chasipexists").val() == 1 || $("#chasiptrunkexists").val() ==1) {
			skip = 'convertall';
			handlelocalrestorefiles(fileid,'upload','',skip);
		} else {
			runRestore(fileid,'Running Restore','',skip);
		}
	});
	$("#runrestorecdr").click(function(e) {
		e.stopPropagation();
		e.preventDefault();
		let skip = null;
		if ($("#chasipexists").val() == 1 || $("#chasiptrunkexists").val() ==1) {
			skip = 'convertall';
			handlelocalrestorefiles(fileid,'uploadlegacy','',skip);
		} else {
			runRestorelegacycdr(fileid,'Running Restore & Legacy CDR Restore',skip);
		}
	});
	if(runningRestore) {
		showStatusModal(_('View running restore'))
		getRestoreStatus(runningRestore.fileid, runningRestore.transaction, runningRestore.pid);
	}

        $('form[name=addBackupJob]').submit(function() {
                var bkjob_name = $("#backup_name").val().trim();
                if(bkjob_name === "") {
                        $("#backup_name").focus();
                        return warnInvalid($("#backup_name"),_("You must set a valid job name for this backup"));
                }
				//start WSS checks
	if ($("#warmspareenabledyes").prop("checked")) {
		if ($("#warmsparewayofrestoreapi").prop("checked")) {//API is enabled
			var server_name = $("#warmspare_remoteapi_filestoreid").val().trim();
			if(server_name === "") {
				$("#warmspare_remoteapi_filestoreid").focus();
				return warnInvalid($("#warmspare_remoteapi_filestoreid"),_("You must select a valid Warm Spare Server"));
			}
			var server_accesstoken = $("#warmspare_remoteapi_accesstokenurl").val().trim();
			if(server_accesstoken === "") {
				$("#warmspare_remoteapi_accesstokenurl").focus();
				return warnInvalid($("#warmspare_remoteapi_accesstokenurl"),_("You must enter a valid Warm Spare Access Token URL"));
			}
			var server_clinetid = $("#warmspare_remoteapi_clientid").val().trim();
			if(server_clinetid === "") {
				$("#warmspare_remoteapi_clientid").focus();
				return warnInvalid($("#warmspare_remoteapi_clientid"),_("You must enter a valid Warm Spare Server API Client ID"));
			}
			var server_clinetserect = $("#warmspare_remoteapi_secret").val().trim();
			if(server_clinetserect === "") {
				$("#warmspare_remoteapi_secret").focus();
				return warnInvalid($("#warmspare_remoteapi_secret"),_("You must enter a valid Warm Spare Server API Client Secret"));
			}
			var server_graphql = $("#warmspare_remoteapi_gql").val().trim();
			if(server_graphql === "") {
				$("#warmspare_remoteapi_gql").focus();
				return warnInvalid($("#warmspare_remoteapi_gql"),_("You must enter a valid Warm Spare Server API GraphQL URL"));
			}
		}
	}
        if (!$('#backupmodules').bootstrapTable('getSelections').length && $('#template_table tr:last').index() ==0) {
			alert(_("No module has selected for this Backup. Please ensure you are selecting atleast Custom files"));
			return false;
		}		
		
		});

        $("#backup_name").on('input', function() {
                var bkjob_name = $("#backup_name").val().trim();
                if ($.inArray(bkjob_name, bkjob_names) != -1) {
                        alert(sprintf(_("The Backup job name %s is already in used, please use a different name."), bkjob_name));
                        return false;
                }
        });


});
//end ready
var moduledisplaysetting = {};
$("#oauthbutton").click(function() {
	event.preventDefault();
	$.post(
		FreePBX.ajaxurl,
		{
			module: "backup",
			command: "accesstoken",
			warmspare_remoteapi_secret: $("#warmspare_remoteapi_secret").val(),
			warmspare_remoteapi_clientid: $("#warmspare_remoteapi_clientid").val(),
			warmspare_remoteapi_accesstokenurl: $("#warmspare_remoteapi_accesstokenurl").val()
		}
	).done(function(data) {
		if(data.status) {var msgjson  = JSON.stringify(data.message);
			var msgjsondec  = JSON.parse(data.message);
			fpbxToast('Access Token Received ');
			$('#warmspare_remoteapi_accesstoken').val(msgjsondec.access_token);
			$('#warmspare_remoteapi_accesstoken_expire').val(msgjsondec.expires_in);
		} else {
			fpbxToast('There was an error in Access token generation ','','error');
		}
	});
})

var deletables = {}
$("table").on('check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table', function () {
	var toolbar = $(this).data("toolbar"),
			id = $(this).prop("id"),
			type = $(this).data("type");
	$("#remove-"+type).prop('disabled', !$("#"+id).bootstrapTable('getSelections').length);
	deletables[type] = $.map($("#"+id).bootstrapTable('getSelections'), function (row) {
		return {
			'id': row.id,
			'file': row.file ? row.file : null
		};
	});
});

$(".btn-remove").click(function() {
	$(this).prop("disabled",true);
	var type = $(this).data("type");
	$.post(
		FreePBX.ajaxurl,
		{
			module: "backup",
			command: "deleteMultipleRestores",
			type: type,
			files: deletables[type]
		}
	).done(function(data) {
		if(data.status) {
			$('#'+type).bootstrapTable('remove', {field: "id", values: data.ids})
		} else {
			fpbxToast(data.message,'','error')
		}
		$(this).prop("disabled",false);
	});
})

$("#backup_backup").on('post-body.bs.table', function () {
	$("#backup_backup .delete").click(function() {
		if(confirm(_('Are you sure you want to delete this item?'))) {
			var id = $(this).data('item');
			$.post(
				FreePBX.ajaxurl,
				{
					module: "backup",
					command: "deleteBackup",
					id: id
				}
			).done(function(data) {
				if(data.status) {
					$('#backup_backup').bootstrapTable('remove', {field: "id", values: [id]})
				} else {
					fpbxToast(data.message,'','error')
				}

			});
		}
	});
	$("#backup_backup .run").click(function() {
		var id = $(this).data('item');
		runBackup(id,'Running Backup');
	});

	$("#backup_backup .view").click(function() {
		var id = $(this).data('item');
		var transaction = $(this).data('transactionId');
		var pid = $(this).data('pid');
		showStatusModal(_('View backup log'))
		getBackupStatus(id, transaction, pid);
	});


});

$("#restoreFiles").on("post-body.bs.table", function () {
	$('#restoreFiles .remoteDelete').on('click', e => {
		e.preventDefault();
		document.body.style.overflowY = "auto";
		fpbxConfirm(
			_("Are you sure you wish to delete this file? This cannot be undone"),
			_("Delete"), _("Cancel"),
			function () {
				var id = e.currentTarget.dataset.id;
				var file = e.currentTarget.dataset.file;
				$.ajax({
					url: ajaxurl,
					method: "GET",
					data: {
						module: 'backup',
						command: 'deleteRemote',
						id: id,
						file: file,
					}
				})
				.then(data => {
					if (data.status) {
						$('#restoreFiles').bootstrapTable('remove', {field: "id", values: [data.id]})
					}
					fpbxToast(data.message);
				});
			}
		);
	});
	$("#restoreFiles .run").click(function() {
		var id = $(this).data('id');
		var filepath = $(this).data('filepath');
		handlelocalrestorefiles(id,'remote',filepath);
	});
});

$("#localrestorefiles").on("post-body.bs.table", function () {
	$('#localrestorefiles .localDelete').on('click', e =>{
		e.preventDefault();
		document.body.style.overflowY = "auto";

		fpbxConfirm(
			_("Are you sure you wish to delete this file? This cannot be undone"),
			_("Delete"),_("Cancel"),
			function(){
				var id = e.currentTarget.id;
				$.ajax({
					url: FreePBX.ajaxurl,
					method: "GET",
					data: {
						module: 'backup',
						command: 'deleteLocal',
						id: id
					}
				})
				.then(data => {
					if(data.status){
						$("#localrestorefiles").bootstrapTable('refresh',{silent:true});
						$("#restoreFiles").bootstrapTable('refresh',{silent:true});
					}
					fpbxToast(data.message);
				})
				.always(function() {
					document.body.style.overflowY = "auto";
				});
			}
		);
	});
	$("#localrestorefiles .run").click(function() {
		var id = $(this).data('id');
		handlelocalrestorefiles(id,'local');
	});
});


async function handlelocalrestorefiles(id,type,filepath='',skip = null) {
	$('#sipmodal').modal('show');
	if(type == 'local' || type == 'remote') {
		$.ajax({
			url: FreePBX.ajaxurl,
			data: {
				module: 'backup',
				command: 'checkchansip',
				fileid: id,
				type: type,
				filepath
			},
		})
		.then(data => {
			if (data.status) {
				if (data.chansipexists || data.chansipTrunkExists) {
					$('#waittxt').hide();
					$('#warntxt').show();
					$('#convertbtn').show();
				} else {
					$('#waittxt').hide();
					$('#warntxt').hide();
					$('#convertbtn').hide();
					$('#restoretxt').show();
					$('#okbtn').show();
				}
			}
		});
		// Wait for either button click in the modal
		const result = await waitForModalButtons('#sipmodal', '#convertbtn', '#cancelbtn', '#okbtn');
		if (result == 'convertbtn') {
			skip = $('#convertchansip').val();
			if(type == 'local') {
				runRestore(id,'Running Local Restore','',skip);
			} else if(type =='remote') {
				runRestore(id,'Running Remote Restore',filepath,skip);
			}
		} else if(result == 'okbtn') {
			if(type == 'local') {
				runRestore(id,'Running Local Restore','');
			} else if(type =='remote') {
				runRestore(id,'Running Remote Restore',filepath);
			}
		}
	} else if(type == 'upload' || type == 'uploadlegacy') {
		if(skip == 'convertall') {
			$('#waittxt').hide();
			$('#warntxt').show();
			$('#convertbtn').show();
		} else {
			$('#waittxt').hide();
			$('#warntxt').hide();
			$('#convertbtn').hide();
			$('#restoretxt').show();
			$('#okbtn').show();
		}
		const result = await waitForModalButtons('#sipmodal', '#convertbtn', '#cancelbtn', '#okbtn');
		if (result === 'convertbtn') {
			if(type == 'upload') {
				runRestore(id,'Running Restore','',skip);
			} else if(type =='uploadlegacy') {
				runRestorelegacycdr(id,'Running Restore & Legacy CDR Restore',skip);
			}
		} else if(result == 'okbtn') {
			if(type == 'upload') {
				runRestore(id,'Running Restore','',skip);
			} else if(type =='uploadlegacy') {
				runRestorelegacycdr(id,'Running Restore & Legacy CDR Restore',skip);
			}
		}
	}
}

// Function to wait for either button click in the modal
function waitForModalButtons(modalId, firstButtonId, cancelButtonId, okbtnId) {
	return new Promise((resolve) => {
		// Attach click event to the first button
		$(firstButtonId).on('click', function() {
			$('#convertchansip').val('convertall');
			$('#sipmodal').modal('hide');
			resolve('convertbtn');  // Resolve the promise
		});

		$(okbtnId).on('click', function() {
			$('#sipmodal').modal('hide');
			resolve('okbtn');  // Resolve the promise
		});

		// Attach click event to the cancel button
		$(cancelButtonId).on('click', function() {
			resolve('cancelButton');  // Resolve the promise
		});
	});
}

if(sessionStorage.getItem("runBackup")) {
	runBackup(sessionStorage.getItem("runBackup"),'Running Backup');
	sessionStorage.removeItem("runBackup");
}

//init storage multiselect
if ($("#backup_storage").length) {
	$('#backup_storage').multiselect({
		disableIfEmpty: true,
		disabledText: _('No Storage Locations'),
		enableFiltering: true,
		includeSelectAllOption: true,
		buttonWidth: '80%',
		enableLazyLoad: true
	});
	//get items
	$.getJSON(`${FreePBX.ajaxurl}?module=backup&command=backupStorage&id=${$("#id").val()}`)
		.done(
			function (data) {
				$('#backup_storage').multiselect('dataprovider', data);
			}
		)
		.fail(
			function (jqxhr, textStatus, error) {
				$('#backup_storage').multiselect('dataprovider', {});
			}
		);
}
modulesettings = {};
$('#itemsSave').on('click', function (e) {
	e.preventDefault();
	if (!$('#backupmodules').bootstrapTable('getSelections').length) {
		alert(_("No module has selected for this Backup. Please ensure you are selecting atleast Custom files"));
	}
	$('#backup_items').val(JSON.stringify(processItems(undefined, {})));
	$('#backupmodules').bootstrapTable('resetSearch');
	$('#backup_modules').text(_("Modules ("+$('#backupmodules').bootstrapTable('getSelections').length+')'))

	$("#itemsModal").modal('hide');
});
$('#itemsModal').on('show.bs.modal', function (e) {
	$("#itemsModal .modal-body").css("height",(window.innerHeight-200)+"px")
	$("#itemsModal .modal-body").css("overflow-y","auto")
})
$('#itemsReset').on('click', function (e) {
	e.preventDefault();
	$('#backupmodules').bootstrapTable('refresh',{silent: true});
	$('#backup_items').val(JSON.stringify(processItems('reset', {})));
})
$('[name="warmspareenabled"]').change(function () {
	toggle_warmspare();
});
$('[name="warmsparewayofrestore"]').change(function () {
	toggle_warmspareconnection();
});

$("#addBackupJob").submit(function( e ) {
	if (!$("#backup_storage option:selected").val()) {
	   alert(_("No storage location selected for Backup. Please select atleast one storage location to save the backup"));
	   return false;
	}
});

$("#run_backup").on('click', function (e) {
	if (!$("#backup_storage option:selected").val()) {
	   alert(_("No storage location selected for Backup. Please select atleast one storage location to save the backup"));
	   return false;
	}
	sessionStorage.setItem("runBackup", $("#id").val());
	$('.fpbx-submit').submit();
});

function runRestorelegacycdr(id,title,skip = null) {
	$.ajax({
		url: FreePBX.ajaxurl,
		data: {
			module: 'backup',
			command: 'runRestore',
			fileid: id,
			skipchansip: skip,
			legacycdrenable:1
		},
	})
	.then(data => {
		if (data.status) {
			showStatusModal(title)
			getRestoreStatus(id, data.transaction, data.pid);
		} else {
			fpbxToast(data.message, _('Error'),'error');
		}
	});
}
function runRestore(id,title,filepath,skip = null) {
	$.ajax({
		url: FreePBX.ajaxurl,
		data: {
			module: 'backup',
			command: 'runRestore',
			fileid: id,
			skipchansip: skip,
			filepath
		},
	})
	.then(data => {
		if (data.status) {
			showStatusModal(title)
			getRestoreStatus(id, data.transaction, data.pid);
		} else {
			fpbxToast(data.message, _('Error'),'error');
		}
	});
}

function runBackup(id,title) {
	$.ajax({
		url: FreePBX.ajaxurl,
		data: {
			module: 'backup',
			command: 'runBackup',
			id: id
		},
	})
	.then(data => {
		if (data.status) {
			showStatusModal(title)
			getBackupStatus(id, data.transaction, data.pid);
		} else {
			fpbxToast(data.message, _('Error'),'error');
		}
	});
}

function showStatusModal(title) {
	//keep the modal on top. disable hiding when clicking the background or the ESC key
	$("#runModal").modal({
		backdrop: 'static',	
		keyboard: false	
	});

	$("#runModal .close").prop("disabled",true);
	$("#runModal .btn-close").prop("disabled",true);
	$("#runModal .modal-title").text(title);
	$("#runModal .modal-body").css("height",(window.innerHeight-200)+"px")
	$("#runModal .modal-body").css("overflow-y","auto")
	$("#runModal .modal-body").html("<pre>"+_("Loading Please Wait")+"</pre>");
}

function toggle_warmspare() {
	if ($('input[name="warmspareenabled"]:checked').val() == 'yes') {
		$(".warmspare").slideDown();
		$(".warmsparessh").slideUp();
		toggle_warmspareconnection();
	} else {
		$(".warmspareapi").slideUp();
		$(".warmsparessh").slideUp();
		$(".warmspare").slideUp();
	}
}
function toggle_warmspareconnection() {
	if ($('input[name="warmsparewayofrestore"]:checked').val() == 'API') {
		$(".warmspareapi").slideDown();
		$(".warmsparessh").slideUp();
	} else {
		$(".warmsparessh").slideDown();
		$(".warmspareapi").slideUp();
	}
}


$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
	if($(e.target).data("name") === 'restore') {
		$("#localrestorefiles").bootstrapTable('refresh',{silent: true});
	}
})

var reconnects = 0;
var maxReconnects = 120;

function getRestoreStatus(id, transaction, pid) {
	reconnects = 0;
	getStatus('restore', id, transaction, pid)
}

function getBackupStatus(id, transaction, pid) {
	reconnects = 0;
	getStatus('backup', id, transaction, pid)
}

function getStatus(type, id, transaction, pid) {
	var source = new EventSource(FreePBX.ajaxurl+"?module=backup&command="+type+"status&id="+id+"&transaction="+transaction+"&pid="+pid, {withCredentials:true});
	source.onerror = function(e) {
		console.warn(e);
		source.close();
		$("#runModal .modal-body pre").append('NETWORK ERROR...see console log for more details');
		if(reconnects > maxReconnects) {
			$("#runModal .modal-body").animate({scrollTop:$(".modal-body")[0].scrollHeight}, 1000);
			$("#runModal .close").prop("disabled",false);
			$("#runModal .btn-close").prop("disabled",false);
			$("#runModal .modal-body").css("overflow-y","auto")
		} else {
			reconnects++;
			$("#runModal .modal-body pre").append("\nAttempting reconnection...");
			getStatus(type, id, transaction, pid);
		}
	};
	source.addEventListener("new-msgs", function(event){
		var data = JSON.parse(event.data);

		console.log(data);
		reconnects = 0;

		if(data.log.length) {
			$("#runModal .modal-body").html('<pre>'+data.log+'</pre>');
		}

		switch(data.status) {
			case 'stopped':
				fpbxToast(sprintf(_('Your %s has finished'),type));
				$("#runModal .modal-body").css("overflow-y","auto");
			break;
			case 'errored':
				fpbxToast(sprintf(_('There was an error during %s'),type),_('Error'),'error');
				$("#runModal .modal-body").css("overflow-y","auto");
			break;
			case 'running':
				$("#runModal .modal-body").animate({scrollTop:$("#runModal .modal-body")[0].scrollHeight}, 1000);
				$("#runModal .modal-body").css("overflow-y", "hidden");
			break;
			default:
			break;
		}

		if(data.status !== 'running') {
			$("#runModal .modal-body").animate({scrollTop:$("#runModal .modal-body")[0].scrollHeight}, 1000);
			source.close();
			$("#runModal .close").prop("disabled",false);
			$("#runModal .btn-close").prop("disabled",false);
			$("#runModal .modal-body").css("overflow-y","auto");
		}
	}, false);
}
let checkedModule = {}
function processItems(type, obj) {
	let items = $('#backupmodules').bootstrapTable('getSelections');
	checkedModule = {
		...checkedModule,
		...obj
	}
	$.each(items, function (i, v) {
		if(Object.keys(checkedModule).length === 0 || checkedModule[v.modulename] === undefined)
			v.settings = type ? [] : $("#modulesetting_"+v.modulename).serializeArray();
		else
			v.settings = type ? [] : checkedModule[v.modulename];
	});
	return items;
}
/** bootstrap tables formatters */
function linkFormatter(value, row, index) {
	let html = `<a href="?display=backup&view=editbackup&id=${value}"><i class="fa fa-pencil"></i></a>`;
	if(runningBackupJobs[row.id]) {
		html += `&nbsp;<a class="clickable view" data-item="${value}" data-transaction-id="${runningBackupJobs[row.id].transaction}" data-pid="${runningBackupJobs[row.id].pid}"><i class="fa fa-eye"></i></a>`;
	} else {
		html += `&nbsp;<a class="clickable run" data-item="${value}"><i class="fa fa-play"></i></a>`;
	}
	html += `&nbsp;<a data-item="${value}" class="clicmd clickable"><i class="fa fa-terminal"></i></a>`;
	html += `&nbsp;<a data-item="${value}" data-index="${index}" class="clickable delete"><i class="fa fa-trash-o"></i></a>`;
	return html;
}

function moduleSettingFilter(index, row) {
	var mname = row.modulename;
	if( mname != "") {
		if (row.settingdisplay) {
			moduledisplaysetting[ mname ] = row.settingdisplay;
		}
	}
	return (row.settingdisplay);
}
function moduleSettingFormatter(index, row, element) {
	var mname = row.modulename;
	if (moduledisplaysetting[ mname ]) {
		return '<div class = "settingdisplay">'+ moduledisplaysetting[ mname ] + '</div>';
	} else {
		return '';
	}
}
/** End formatters */

//TODO:Copy to clipboard if supported
$(document).on('click', '.clicmd', function (e) {
	e.preventDefault();
	window.prompt(_('Run the following in the CLI'), `fwconsole bu --backup ${$(this).data('item')}`);
});

function localLinkFormatter(value, row, index) {
	var html = '<a class="clickable run" data-id="' + row.id + '"><i class="fa fa-play"></i></a>';
	html += '<a href="/admin/api/backup/localdownload?id=' + row.id + '" class="localdownload"><i class="fa fa-download"></i></a>';
	html += '&nbsp;<a href="#" id="' + row.id + '" class="localDelete"><i class="fa fa-trash-o"></i></a>';
	return html;
}

function remoteFormatter(value, row, index) {
	var html = '<a class="clickable run" data-id="' + row.id + '" data-filepath="' + row.file + '"><i class="fa fa-play"></i></a>';
	html += `<a href="/admin/api/backup/remotedownload?id=${row.id}&filepath=${row.file}" class="remotedownload"><i class="fa fa-download"></i></a>`;
	html += `<a href="#" data-id = "${row.id}" data-file = "${row.file}" class="remoteDelete delitem"><i class = "fa fa-trash-o"></i></a>`;

	return html;
}

function timestampFormatter(value, row, index) {
	return moment.unix(value).format(datetimeformat)
}

function sizeFormatter(value, row, index) {
	if (!isNaN(value) && value >= 0)
	{
		var i = Math.floor( Math.log(value) / Math.log(1024) );
		return ( value / Math.pow(1024, i) ).toFixed(2) * 1 + ' ' + ['B', 'KB', 'MB', 'GB', 'TB'][i];
	}
	else
	{
		value = _("NA");
	}
	return value
}

$("#backup-side").on("click-row.bs.table", function(event, row) {
	window.location = "?display=backup&view=editbackup&id="+row.id;
});

$('#backup_modules').text(_("Loading ..."));
$('#backupmodules').bootstrapTable({
    onLoadSuccess: function() {
		$('#backup_items').val(JSON.stringify(processItems(undefined, {})));
		$('#backup_modules').text(_("Modules ("+$('#backupmodules').bootstrapTable('getSelections').length+')'));
    }
});

var pkFromAutoSync = true;
var PK_SSH_RESTRICT_SCRIPT = '/usr/local/bin/freepbx-ssh-restrict.sh';
var PK_SSH_FIXED_OPTIONS = ['restrict'];

function isPkCommandRestrictionEnabled() {
	return typeof window.PK_SSH_COMMAND_RESTRICTION_ENABLED !== 'undefined'
		&& window.PK_SSH_COMMAND_RESTRICTION_ENABLED;
}

function escapeSshOptionValue(value) {
	return value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
}

function getPkModalSshOptions() {
	var from = $('#pkFrom').val().trim();
	var options = {
		restrict: true
	};
	if (isPkCommandRestrictionEnabled()) {
		options.command = PK_SSH_RESTRICT_SCRIPT;
	}
	if (from) {
		options.from = from;
	}
	return options;
}

function buildAuthorizedKeysLine(publicKey, sshOptions) {
	var key = (publicKey || '').trim();
	if (!key) {
		return '';
	}
	var opts = sshOptions || {};
	if (!opts.from) {
		return '';
	}
	var parts = PK_SSH_FIXED_OPTIONS.slice();
	if (isPkCommandRestrictionEnabled()) {
		parts.push('command="' + escapeSshOptionValue(opts.command || PK_SSH_RESTRICT_SCRIPT) + '"');
	}
	parts.push('from="' + escapeSshOptionValue(opts.from) + '"');
	return parts.join(',') + ' ' + key;
}

function summarizePkRestrictions(sshOptions) {
	var opts = sshOptions || {};
	var parts = PK_SSH_FIXED_OPTIONS.slice();
	if (isPkCommandRestrictionEnabled()) {
		parts.push('command=' + (opts.command || PK_SSH_RESTRICT_SCRIPT));
	}
	if (opts.from) {
		parts.push('from=' + opts.from);
	}
	return parts.join(', ');
}

function updatePkAuthorizedPreview() {
	var publicKey = $('#pkPublicKey').val().trim();
	var from = $('#pkFrom').val().trim();
	if (!publicKey) {
		$('#pkAuthorizedPreview').text(_('Enter a public key to preview the authorized_keys line'));
		return;
	}
	if (!from) {
		$('#pkAuthorizedPreview').text(_('Enter From to preview the authorized_keys line'));
		return;
	}
	$('#pkAuthorizedPreview').text(buildAuthorizedKeysLine(publicKey, getPkModalSshOptions()));
}

function resetAddPublicKeyModal() {
	pkFromAutoSync = true;
	$('#pkServerName, #pkPublicKey, #pkFrom').val('');
	updatePkAuthorizedPreview();
}

$('#addPublicKeyModal').on('show.bs.modal', function() {
	resetAddPublicKeyModal();
	$('#addPublicKeyModal .fpbx-help-block').removeClass('active');
});

$('#addPublicKeyModal').on('mouseenter', 'i.fpbx-help-icon', function() {
	var id = $(this).data('for');
	var container = $(this).closest('.element-container');
	container.find('.fpbx-help-block').removeClass('active');
	$('#' + id + '-help').addClass('active');
	container.one('mouseleave', function() {
		container.find('.fpbx-help-block.active').fadeOut('fast', function() {
			$(this).removeClass('active').css('display', '');
		});
	});
});

$('#pkServerName').on('input', function() {
	var serverName = $(this).val().trim();
	if (pkFromAutoSync) {
		$('#pkFrom').val(serverName);
	}
	updatePkAuthorizedPreview();
});

$('#pkFrom').on('input', function() {
	pkFromAutoSync = $(this).val().trim() === $('#pkServerName').val().trim();
	updatePkAuthorizedPreview();
});

$('#pkPublicKey').on('input', updatePkAuthorizedPreview);

function appendPublicKeyTableRow(servername, displayKey, authorizedLine, restrictionsSummary) {
	var $row = $('<tr>').attr('data-authorized-line', authorizedLine);
	$row.append($('<td>').append(
		$('<input>', { type: 'text', name: 'servername[]', class: 'form-control', readonly: true, value: servername })
	));
	$row.append($('<td>').append(
		$('<textarea>', { name: 'publickeyAsteriskUser[]', class: 'form-control', rows: 4, readonly: true }).val(displayKey)
	));
	$row.append($('<td>', { class: 'pk-restrictions-cell' }).append(
		$('<span>', { class: 'text-muted', text: restrictionsSummary })
	));
	$row.append($('<td>').append(
		$('<button>', { type: 'button', class: 'btn btn-danger deleteRow', text: _('Delete') })
	));
	$('#serverTable tbody').append($row);
}

$('#pkModalSave').on('click', function() {
	var servername = $('#pkServerName').val().trim();
	var publicKey = $('#pkPublicKey').val().trim();
	var sshOptions = getPkModalSshOptions();
	var authorizedLine = buildAuthorizedKeysLine(publicKey, sshOptions);

	if (!servername) {
		fpbxToast(_('Server name is required'), _('Error'), 'error');
		$('#pkServerName').focus();
		return;
	}
	if (!publicKey) {
		fpbxToast(_('Public key is required'), _('Error'), 'error');
		$('#pkPublicKey').focus();
		return;
	}
	if (!/^(ssh-rsa|ssh-ed25519|ecdsa)\b/.test(publicKey)) {
		fpbxToast(_('Invalid public key format'), _('Error'), 'error');
		return;
	}
	if (!sshOptions.from) {
		fpbxToast(_('From is required (IP, hostname, or comma-separated list)'), _('Error'), 'error');
		$('#pkFrom').focus();
		return;
	}

	if (!confirm(_('Are you sure you want to save this public key?'))) {
		return;
	}

	$.post(FreePBX.ajaxurl, {
		module: 'backup',
		command: 'publicKeySave',
		publickeyAsteriskUser: authorizedLine,
		publickey: publicKey,
		servername: servername,
		sshOptions: JSON.stringify(sshOptions)
	}).done(function(data) {
		if (data.status) {
			appendPublicKeyTableRow(
				servername,
				publicKey,
				data.publickeyAsteriskUser || authorizedLine,
				data.restrictionsSummary || summarizePkRestrictions(sshOptions)
			);
			$('#addPublicKeyModal').modal('hide');
			fpbxToast(_('Public key saved successfully'));
		} else {
			fpbxToast(data.message, _('Error'), 'error');
		}
	});
});

$('#serverTable').on('click', '.deleteRow', function() {
	var tr = $(this).closest('tr');
	var authorizedLine = tr.data('authorized-line') || tr.find('textarea').val().trim();
	if (!authorizedLine) {
		tr.remove();
		return;
	}
	if (!confirm(_('Are you sure you want to delete this public key?'))) {
		return;
	}
	$.post(FreePBX.ajaxurl, {
		module: 'backup',
		command: 'publicKeyRemove',
		keyToRemove: authorizedLine
	}).done(function(data) {
		if (data.status) {
			fpbxToast(_('Public key deleted successfully'));
			tr.remove();
		} else {
			fpbxToast(data.message, _('Error'), 'error');
		}
	});
});
