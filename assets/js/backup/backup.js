//put all document ready stuff here... One listener to rule them all
$(document).ready(function(){
	//init storage multiselect
	if ($("#backup_storage").length){
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
			function(data){
				$('#backup_storage').multiselect('dataprovider',data);
			}
		)
		.fail(
			function( jqxhr, textStatus, error ){
				console.log(textStatus);
				$('#backup_storage').multiselect('dataprovider',{});
			}
		);
	}
	modulesettings = {};
	$('#itemsSave').on('click', function(e){
		e.preventDefault();
		$('#backup_items').val(JSON.stringify(processItems()));
		$("#itemsModal").modal('hide');
	});
	$('#itemsReset').on('click',function(e){
		e.preventDefault();
		$('#backupmodules').bootstrapTable('refresh');
	})
	$('#backupmodules').on('expand-row.bs.table',function(i,r){
		$('.hooksetting :input').on('change',function(e){
			var obj = $(this).serializeArray()[0];
			modulesettings[obj.name] = obj.value;
			$('#backup_items_settings').val(JSON.stringify(modulesettings));
		});
	});
});
//end ready

function processItems(){
	let items = $('#backupmodules').bootstrapTable('getData');
	let selected = items.filter(function (el){ return el.selected == true;})
	 $.each(selected,function(i,v){
		if (v.hasOwnProperty('settingdisplay')) {
			delete v.settingdisplay;
		}
	});
	return selected;
}
function linkFormatter(value, row, index){
		let html = `<a href="?display=backup&view=form&id=${value}"><i class="fa fa-pencil"></i></a>`;
				html += `&nbsp;<a href="" data-item="${value}" class="run"><i class="fa fa-play"></i></a>`;
				html += `&nbsp;<a href="" data-item="${value}" class="clicmd"><i class="fa fa-terminal"></i></a>`;
				html += `&nbsp;<a href="?display=backup&action=delete&id=${value}" class="delAction"><i class="fa fa-trash"></i></a>`;
		return html;
}

function moduleSettingFormatter(i,r,e){
	if(r.settingdisplay){
		return `<div class = "settingdisplay">${r.settingdisplay}</div>`;
	}else{
		return _("This module has no settings");
	}
}

$(document).on('click','.run',function(e){
	e.preventDefault();
	alert($(this).data('item'))
});

$(document).on('click','.clicmd',function(e){
	e.preventDefault();
	window.prompt(_('Run the following in the CLI'),`fwcosole bu --backup ${$(this).data('item')}`);
});
