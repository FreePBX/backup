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
        console.log(data);
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
  //init items multiselect
  if ($("#backup_items").length){
    $('#backup_items').multiselect({
      disableIfEmpty: true,
      disabledText: _('No Backup Items'),
      enableFiltering: true,
      includeSelectAllOption: true,
      buttonWidth: '80%'
    });
    $.get(`ajax.php?module=backup&command=getJSON&jdata=backupItems&id=${$("#id").val()}`,function(data){ $('#backup_items').multiselect('dataprovider',data);});

  }
});
//end ready

function linkFormatter(value, row, index){
    let html = `<a href="?display=backup&view=form&id=${value}"><i class="fa fa-pencil"></i></a>`;
        html += `&nbsp;<a href="" data-item="${value}" class="run"><i class="fa fa-play"></i></a>`;
        html += `&nbsp;<a href="" data-item="${value}" class="clicmd"><i class="fa fa-terminal"></i></a>`;
        html += `&nbsp;<a href="?display=backup&action=delete&id=${value}" class="delAction"><i class="fa fa-trash"></i></a>`;
    return html;
}

$(document).on('click','.run',function(e){
  e.preventDefault();
  alert($(this).data('item'))
});

$(document).on('click','.clicmd',function(e){
  e.preventDefault();
  window.prompt(_('Run the following in the CLI'),`fwcosole bu --backup ${$(this).data('item')}`);
});
