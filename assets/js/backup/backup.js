//put all document ready stuff here... One listener to rule them all
$(document).ready(function () {
	toggle_warmspare();
	//init storage multiselect
	if ($("#backup_storage").length) {
		$('#backup_storage').multiselect({
			disableIfEmpty: true,
			disabledText: _('No Storage Locations'),
			enableFiltering: true,
			includeSelectAllOption: true,
			buttonWidth: '80%'
		});
		//get items
		$.getJSON(`ajax.php?module=backup&command=getJSON&jdata=backupStorage&id=${$("#id").val()}`)
			.done(
				function (data) {
					$('#backup_storage').multiselect('dataprovider', data);
				}
			)
			.fail(
				function (jqxhr, textStatus, error) {
					console.log(textStatus);
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
	$('#itemsReset').on('click', function (e) {
		e.preventDefault();
		$('#backupmodules').bootstrapTable('refresh');
	})
	$('#backupmodules').on('expand-row.bs.table', function (i, r) {
        /** Delay for dynamic contenet so we don't miss the bind on expand. */
        setTimeout(function(){
    		$('.hooksetting :input').on('change', function (e) {
                var obj = $(this).serializeArray()[0];
                if(obj){
                    modulesettings[obj.name] = obj.value;
                }else{
                    modulesettings[e.currentTarget.name] = '';
                }
			    $('#backup_items_settings').val(JSON.stringify(modulesettings));
            });
        },400);
	});
	$('[name="warmspareenabled"]').change(function () {
		toggle_warmspare();
	});
	$("#run_backup").on('click', function (e) {
		var input = $("<input>")
			.attr("type", "hidden")
			.attr("name", "runit").val(true);
		$('.fpbx-submit').append(input);
		$('#submit').click();
	});


	$("#backup_backup").on('post-body.bs.table', function () {
		$(".run").on('click', function (e) {
			e.preventDefault();
			$("#loadingimg").removeClass('hidden');
			let transaction = false;
			let id = $(this).data('item');
			fpbxToast(_("Submitting backup job"));
			$(".run").each(function () {
				if ($(this).data('item') == id) {
					$(this).children(":first").removeClass('fa-play').addClass('fa-spinner fa-spin');
				}
			});
			$('#backuplog').modal('show');
			$.ajax({
				url: ajaxurl,
				data: {
					module: 'backup',
					command: 'run',
					id: $(this).data('item')
				},
			})
			.then(data => {
				if(data.status){
					lockButtons(data.backupid,data.transaction);
				}
			});
		});
	});
});
//end ready
function toggle_warmspare() {
	if ($('input[name="warmspareenabled"]:checked').val() == 'yes') {
		$(".warmspare").slideDown();
	} else {
		$(".warmspare").slideUp();
	}
}

function lockButtons(id, transaction) {

	var checkit = setInterval(function () {
		$.ajax({
			url: ajaxurl,
			data:{
				module: 'backup',
				command: 'runstatus',
				id: id,
				transaction: transaction
			}
			})
			.then(data => {
				console.log(data);
				if(data.message){
					fpbxToast(data.message);	
				}
				if(data.log){
					$("#logtext").html(data.log);
				}
				if (data.status == 'stopped') {
					$("#loadingimg").addClass('hidden');
					fpbxToast(_('Your backup has finished'));
					clearInterval(checkit);
				}
			})
			.fail(err => {
				$(".run").each(function () {
					$("#loadingimg").addClass('hidden');
					$(this).removeClass('disabled');
					$(this).children(":first").removeClass('fa-spinner fa-spin').addClass('fa-play');
					clearInterval(checkit);
				});
			});
	}, 1100);
}

function processItems() {
	let items = $('#backupmodules').bootstrapTable('getData');
	let selected = items.filter(function (el) {
		return el.selected == true;
	})
	$.each(selected, function (i, v) {
		if (v.hasOwnProperty('settingdisplay')) {
			delete v.settingdisplay;
		}
	});
	return selected;
}
/** bootstrap tables formatters */
function linkFormatter(value, row, index) {
	let html = `<a href="?display=backup&view=form&id=${value}"><i class="fa fa-pencil"></i></a>`;
	html += `&nbsp;<a href="?display=backup&view=run&id=${value}" data-item="${value}"><i class="fa fa-play" id="${value}"></i></a>`;
	html += `&nbsp;<a href="" data-item="${value}" class="clicmd"><i class="fa fa-terminal"></i></a>`;
	html += `&nbsp;<a href="?display=backup&action=delete&id=${value}" class="delAction"><i class="fa fa-trash"></i></a>`;
	return html;
}

function moduleSettingFormatter(value, row, index) {
	if (row.settingdisplay) {
		return `<div class = "settingdisplay">${row.settingdisplay}</div>`;
	} else {
		return _("This module has no settings");
	}
}
/** End formatters */

//TODO:Copy to clipboard if supported
$(document).on('click', '.clicmd', function (e) {
	e.preventDefault();
	window.prompt(_('Run the following in the CLI'), `fwconsole bu --backup ${$(this).data('item')}`);
});
