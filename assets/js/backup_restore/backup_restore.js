$(document).ready(() => {

  $("#backupUpload").click( event => {
    event.preventDefault();
    let file = $("#filetorestore")[0].files[0];
    var formData = new FormData();
    formData.append('filetorestore', file);
    $.ajax({
      url: `${ajaxurl}?module=backup&command=uploadrestore`,
      type: 'POST',
      data: formData,
      processData: false,  // tell jQuery not to process the data
      contentType: false  // tell jQuery not to set contentType
    })
    .then(data => {
      if(data.status == true){
        let url = `${window.location.href}&view=processrestore&id=${data.id}`;
        console.log(url);
        //window.location = url;
      }else{
        fpbxToast(data.message, 'error');
      }
    })
    .fail(err =>{
      fpbxToast("Unable to upload File");
      console.log(err);
      return false;
    });
    return false;
  });

});//end document ready
