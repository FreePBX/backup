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
				$("#uploadrestore").html(sprintf(_("Uploading %s/100"),parseInt(progress))+'<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>')
			} else {
				$("#uploadrestore").html(_("Processing...")+'<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>')
			}

			$("#uploadprogress").css('width', `${progress}%`);
		});
	}

	$("#runrestore").click(function(e) {
		e.stopPropagation();
		e.preventDefault();
		runRestore(fileid,'Running Restore');
	});
});
//end ready

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
		runRestore(id,'Running Remote Restore',filepath);
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
		runRestore(id,'Running Local Restore');
	});
});

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
	$('#backup_items').val(JSON.stringify(processItems()));
	$("#itemsModal").modal('hide');
});
$('#itemsModal').on('show.bs.modal', function (e) {
	$("#itemsModal .modal-body").css("height",(window.innerHeight-200)+"px")
	$("#itemsModal .modal-body").css("overflow-y","auto")
})
$('#itemsReset').on('click', function (e) {
	e.preventDefault();
	$('#backupmodules').bootstrapTable('refresh',{silent: true});
})
$('[name="warmspareenabled"]').change(function () {
	toggle_warmspare();
});
$("#run_backup").on('click', function (e) {
	sessionStorage.setItem("runBackup", $("#id").val());
	$('.fpbx-submit').submit();
});

function runRestore(id,title,filepath) {
	$.ajax({
		url: FreePBX.ajaxurl,
		data: {
			module: 'backup',
			command: 'runRestore',
			fileid: id,
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
	$("#runModal").modal('show')
	$("#runModal .btn-close").prop("disabled",true);
	$("#runModal .modal-title").text(title);
	$("#runModal .modal-body").css("height",(window.innerHeight-200)+"px")
	$("#runModal .modal-body").css("overflow-y","hidden")
	$("#runModal .modal-body").html("<pre>"+_("Loading Please Wait")+"</pre>");
}

function toggle_warmspare() {
	if ($('input[name="warmspareenabled"]:checked').val() == 'yes') {
		$(".warmspare").slideDown();
	} else {
		$(".warmspare").slideUp();
	}
}

$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
	if($(e.target).data("name") === 'restore') {
		$("#localrestorefiles").bootstrapTable('refresh',{silent: true});
	}
})

var reconnects = 0;
var maxReconnects = 10;

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

		if(data.log.length) {
			$("#runModal .modal-body").html('<pre>'+data.log+'</pre>');
		}

		switch(data.status) {
			case 'stopped':
				fpbxToast(sprintf(_('Your %s has finished'),type));
			break;
			case 'errored':
				fpbxToast(sprintf(_('There was an error during %s'),type),_('Error'),'error');
			break;
			case 'running':
				$("#runModal .modal-body").animate({scrollTop:$(".modal-body")[0].scrollHeight}, 1000);
			break;
			default:
			break;
		}

		if(data.status !== 'running') {
			$("#runModal .modal-body").animate({scrollTop:$(".modal-body")[0].scrollHeight}, 1000);
			source.close();
			$("#runModal .btn-close").prop("disabled",false);
			$("#runModal .modal-body").css("overflow-y","auto")
		}
	}, false);
}

function processItems() {
	let items = $('#backupmodules').bootstrapTable('getData');
	let selected = items.filter(function (el) {
		return el.selected == true;
	})
	$.each(selected, function (i, v) {
		if (v.hasOwnProperty('settingdisplay')) {
			delete v.settingdisplay;
			v.settings = $("#modulesetting_"+v.modulename).serializeArray();
		}
	});
	return selected;
}
/** bootstrap tables formatters */
function linkFormatter(value, row, index) {
	let html = `<a href="?display=backup&view=editbackup&id=${value}"><i class="fa fa-pencil"></i></a>`;
	html += `&nbsp;<a class="clickable run" data-item="${value}"><i class="fa fa-play"></i></a>`;
	html += `&nbsp;<a data-item="${value}" class="clicmd clickable"><i class="fa fa-terminal"></i></a>`;
	html += `&nbsp;<a data-item="${value}" data-index="${index}" class="clickable delete"><i class="fa fa-trash"></i></a>`;
	return html;
}

function moduleSettingFilter(index, row) {
	return (row.settingdisplay);
}

function moduleSettingFormatter(index, row, element) {
	if (row.settingdisplay) {
		return `<div class = "settingdisplay">${row.settingdisplay}</div>`;
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
	html += '&nbsp;<a href="#" id="' + row.id + '" class="localDelete"><i class="fa fa-trash"></i></a>';
	return html;
}

function remoteFormatter(value, row, index) {
	var html = '<a class="clickable run" data-id="' + row.id + '" data-filepath="' + row.file + '"><i class="fa fa-play"></i></a>';
	html += `<a href="/admin/api/backup/remotedownload?id=${row.id}&filepath=${row.file}" class="remotedownload"><i class="fa fa-download"></i></a>`;
	html += `<a href="#" data-id = "${row.id}" data-file = "${row.file}" class="remoteDelete delitem"><i class = "fa fa-trash"></i></a>`;

	return html;
}

function timestampFormatter(value, row, index) {
	return moment.unix(value).format(datetimeformat)
}


$("#backup-side").on("click-row.bs.table", function(event, row) {
	window.location = "?display=backup&view=editbackup&id="+row.id;
});
