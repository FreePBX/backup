$(document).ready(function(){
	//resotre
	restore();
	$('select[name=bu_server]').change(restore)
	//cron_custom
	cron_custom();
	$('select[name=cron_schedule]').change(cron_custom);
	
	//cron_schedule
	cron_random();
	$('select[name=cron_schedule]').change(cron_random);
	
	//storage servers
	$('#storage_used_servers').sortable({
		connectWith: '.storage_servers',
		update: save_storage_servers,
	}).disableSelection();
	
	$('#storage_avail_servers').sortable({
		connectWith: '.storage_servers'
	}).disableSelection();
	
	//templates
	$('#templates > li').draggable({
		revert: true,
		cursor: 'move'
	}).disableSelection();
	
	$('#template_table').droppable({
		drop: function(event, ui) {
			current_items_over_helper('show');
			var data = JSON.parse(decodeURIComponent(ui.draggable.data('template')));
			add_template(data);
		},
		over: function(event, ui) {
			current_items_over_helper('hide');
		},
		out: function(event, ui) {
			current_items_over_helper('show');
		}
	});
	//run backup
	$('#run_backup').click(function(){

		id = $('#backup_form').find('[name=id]').val();
		if (typeof id == 'undefined' || !id) {
			return false;
		} 
		 box = $('<div></div>')
			.html('<span id="backup_status"></span>'
				+ '<progress style="width: 100%">'
				+ 'Please wait...'
				+ '</progress>')
			.dialog({
				title: 'Run backup',
				resizable: false,
				modal: true,
				position: ['center', 50],
				width: 500,
				close: function (e) {
					$(e.target).dialog("destroy").remove();
				}
			});
		url = window.location.pathname 
			+ '?display=backup&action=run&id=' + id
		
		if (!window.EventSource) {
			$.get(url, function(){
				$('#backup_status').next('progress').append('done!');
				setTimeout('box.dialog("close").dialog("destroy").remove();', 5000);
			});
			return false;
		}
		var eventSource = new EventSource(url);
		eventSource.addEventListener('message', function (event) {
			console.log(event.data);
			if (event.data == 'END') {
				eventSource.close();
				$('#backup_status').next('progress').remove();
				//setTimeout('box.dialog("close").dialog("destroy").remove();', 5000);
			} else {
				$('#backup_status').append(event.data + '<br>');
			}
		}, false);
		eventSource.addEventListener('onerror', function (event) {
		    console.log('e', event.data);
		}, false);
		return false;
	});

	//style cron custom times
	$('#crondiv').find('input[type=checkbox]').button()
})
function restore() {
	if ($('select[name=bu_server]').val() == 0) {
		$('#restore').removeAttr("checked");
		$('.restore').hide()
	} else {
		$('.restore').show()
	}
}

function cron_custom() {
	if ($('select[name=cron_schedule]').val() == 'custom') {
		$('#crondiv').show();
	} else {
		$('#crondiv').hide();
	}
}

function cron_random() {
	switch($('select[name=cron_schedule]').val()) {
		case 'never':
		case 'custom':
		case 'reboot':
			$('label[for=cron_random]').hide();
			$('#cron_random').removeAttr("checked").hide();
			break;
		default:
			$('label[for=cron_random]').show();
			$('#cron_random').show();
			break;
	}
}

function save_storage_servers(){
	$('#backup_form > input[name^=storage_servers]').remove();
	$('#storage_used_servers > li').each(function(){
		field		= document.createElement('input');
		field.name	= 'storage_servers[]';
		field.type	= 'hidden';
		field.value	= $(this).data('server-id');
		$('#backup_form').append(field);
	})
}


function current_items_over_helper(action) {
	switch (action) {
		case 'show':
			$('#items_over').hide();
			$('#template_table').show();
			$('#add_entry').show();
			break;
		case 'hide':
			width = $('#template_table').width();
			height = $('#template_table').height();
			height2 = $('#templates').height();
			
			$('#items_over').width(width - 10);
			$('#items_over').height(height > height2 ? height : height2);
			
			
			$('#template_table').hide();
			$('#add_entry').hide();
			$('#items_over').show();
			break;
	}
}
function add_template(template) {

	
	//clone the object so that we dont destroy the origional when we delete from it
	var template = $.extend({}, template);
	for (var item in template) {
		if (!template.hasOwnProperty(item)) { //skip non properties, such as __proto__
			continue;
		}
		$('#template_table > tbody > tr:not(:first)').each(function(){
			row = {};
			row.type 	= $(this).find('td').eq(0).find('input').val();
			if ($(this).find('td').eq(1).find('select').length > 0) {
				row.path = $(this).find('td').eq(1).find('select').val();
			} else if ($(this).find('td').eq(1).find('input').length > 0) {
				row.path = $(this).find('td').eq(1).find('input').val();
			} else {
				row.path = '';
			}

			row.exclude	= $(this).find('td').eq(2).find('textarea').val() || '';
			if (row.type == template[item].type && row.path == template[item].path) {
				//merge excludes if we have any
				if (template[item].exclude) {
					//merge current and template's exclude
					row.exclude = row.exclude.split("\n") //split string by line breaks
									.concat(template[item].exclude) //merge template and row
									.filter(function(element){return element}) //remove blanks
									.sort() 
									.filter(function(element, index, array){ //remove duplicates
										if ($.trim(element) != $.trim(array[index + 1])) {
											return $.trim(element);
										}
									});
					
					//add excludes to row
					$(this).find('td').eq(2).find('textarea')
							.attr('rows',row.exclude.length)
							.val(row.exclude.join("\n"));
				}

				delete template[item];
				return false;
			}

		});	
	}

	//add new items
	if (typeof template != "undefined") {
		for (var item in template) {
			if (!template.hasOwnProperty(item)) {
				continue;
			}
			add_template_row(template[item].type);
			new_row = $('#template_table > tbody:last').find('tr:last');
			if (new_row.find('td').eq(1).find('select').length > 0) {
				new_row.find('td').eq(1).find('select').val(template[item].path);
			} else if (new_row.find('td').eq(1).find('input').length > 0) {
				new_row.find('td').eq(1).find('input').val(template[item].path);
			}
			new_row.find('td').eq(2).find('textarea').val(template[item].exclude)
		}
	}

}
