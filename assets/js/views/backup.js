function remote() {
	if ($('#bu_server').val() == 0) {
		$('#restore').removeAttr("checked");
		$('.remote').hide()
	} else {
		$('.remote').show();
		restore();
	}
};
function restore() {
	if ($('#restoreyes').is(':checked')) {
			$('.restore').show();
		} else {
			$('.restore').hide();
		}
}
$(document).ready(function(){

	//have to insert a delay otherwise it runs too soon.
	setTimeout(remote,1000);
	//setTimeout(restore,1000);
	$('#bu_server').on('change',function(){
		remote();
	});

	restore();
	$('input[id^="restore"]').change(restore);
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

	$('#template_table, #items_over').droppable({
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
		run_backup(id);
	});

	//style cron custom times
	$('#crondiv').find('input[type=checkbox]').button();

	//highlight save when run is hovered
	$('#run_backup').hover(
		function(){
			$('#save_backup').addClass('ui-state-hover');
		},
		function(){
			$('#save_backup').removeClass('ui-state-hover');
		}
	);
});


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
			$('#randominput').hide();
			$('#cron_random').removeAttr("checked").hide();
			break;
		default:
			$('#randominput').show();
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
			new_row.find('td').eq(2).find('textarea').val(template[item].exclude.join("\n"))
		}
	}

}
