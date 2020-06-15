$(document).ready(function(){
	//delete rows on click
	$(document).on('click', '.delete_entrie', function(){
		$(this).closest('tr').fadeOut('normal', function(){$(this).closest('tr').remove();})
	});

	//move add row's select box to right after the add button - easier to show and hide that way
	$('select[name=add_tr_select]').insertAfter($('#add_entry'));

	//show select when add is clicked
	$('#add_entry').click(function(){
		$(this).hide();
		$('select[name=add_tr_select]').val('').show();
	});

	//hide select and add a new row when add_tr_select is selected
	$('select[name=add_tr_select]').change(function(){
		$(this).hide();
		$('#add_entry').show();
		add_template_row($(this).val());
	});
	function validate(){
		$('#template_table').closest('form').submit(function(){
			var table = [];
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

				//ensure all "excludes" are unique
				var ta = $(this).find('td').eq(2).find('textarea').val();
				if(typeof ta !== "undefined") {
					row.exclude	= ta.split("\n")
									.filter(function(element){return element})
									.sort()
									.filter(function(element, index, array){
										if ($.trim(element) != $.trim(array[index + 1])) {
											return $.trim(element);
										}
									})
									.join("\n");
					$(this).find('td').eq(2).find('textarea').val(row.exclude);
				}

				row.index = $(this).index();
				table.push(row);
			});

			//loop through the array twice, ignore the same index (they will always match!)
			//if there is a duplicate, sound the alarm
			for (var row in table) {
				for (var hash in table) {
					if (table[row].index != table[hash].index
						&& table[row].type == table[hash].type
						&& table[row].path == table[hash].path
					) {
						alert('Duplicate items detected. Lines: ' + table[row].index + ', ' + table[hash].index);

						//scroll to the heighest problimatic line
						line = table[row].index < table[hash].index ? table[row].index : table[hash].index;
						var scrollto = $('#template_table > tbody > tr').eq(line).offset().top;
						$('body,html').animate({scrollTop:scrollto}, 500);

						//dont submit the page
						return false;
					}
				}
			}

		});
	}
	validate();
	$('#backup_template').submit(validate);
})

var row_index = 1000; //on purpose dont change!
function add_template_row(type) {
	//add row
	tr = template_tr[type].replace(/TR_UID/g, row_index);
	$('#template_table > tbody:last').find('tr:last').after(tr);
	row_index++;
}
